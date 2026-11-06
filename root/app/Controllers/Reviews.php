<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * The reviews customers wrote of the session business's products and services, from LINK_INTERNAL_API
 * v1/client/artifact-review*: anyone working in the business, its employees included, sees them and approves or
 * hides them, as the API allows. Approving one shows it to other customers and counts it towards the rating; hiding
 * one takes it down.
 */
class Reviews extends BaseController
{
    private const STATUS_PENDING  = 'P';
    private const STATUS_APPROVED = 'A';
    private const STATUS_HIDDEN   = 'H';

    /** The most stars of a rating */
    private const MAX_RATING = 5;

    /**
     * The reviews (generic_list), newest first, with the Approve and Hide buttons of each
     */
    public function index(): string
    {
        $config = $this->listConfig();
        return view('generic_list', [
            'locale'       => $this->request->getLocale(),
            'slug'         => 'reviews',
            'user'         => (array) session('user'),
            'listConfig'   => $config,
            'customScript' => view('scripts/reviews', [
                'tableId' => $config['table_id'],
                'urls'    => ['approve' => base_url('admin/reviews/approve'), 'hide' => base_url('admin/reviews/hide')],
                'failed'  => lang('Reviews.action-failed'),
            ]),
        ]);
    }

    /**
     * DataTables data of index(): one page from LINK_INTERNAL_API v1/client/artifact-reviews { business_slug, start,
     * length, filters }, which pages them itself, newest first (no search or other order)
     */
    public function data(): ResponseInterface
    {
        $config  = $this->listConfig();
        $params  = lb_request($this->request, $config);
        $filters = [];
        foreach ($params['filters'] as $filter) {
            $filters[$filter['def']['name']] = (string) $filter['value'];
        }
        [$code, $body] = $this->callApi('v1/client/artifact-reviews', [
            'business_slug' => (string) currentBusinessSlug(),
            'start'         => $params['start'],
            'length'        => $params['length'],
            'filters'       => (object) $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + [
                'error'     => $body['message'] ?? lang('Reviews.load-failed'),
                'csrf_hash' => csrf_hash(),
            ]);
        }

        $rows = [];
        foreach ((array) ($body['data']['rows'] ?? []) as $review) {
            $status = (string) ($review['review_status'] ?? '');
            $rows[] = [
                'public_id'     => (string) ($review['public_id'] ?? ''),
                'created_at'    => (string) ($review['created_at'] ?? ''),
                'artifact_name' => (string) ($review['artifact_name'] ?? ''),
                'customer_name' => (string) ($review['customer_name'] ?? ''),
                'rating'        => self::stars((int) ($review['rating'] ?? 0)),
                'review'        => self::reviewHtml($review),
                'review_status' => $status,
                'actions'       => $this->actions((string) ($review['public_id'] ?? ''), $status),
            ];
        }
        return $this->response->setJSON(lb_response(
            $params['draw'],
            (int) ($body['data']['records_total'] ?? 0),
            (int) ($body['data']['records_filtered'] ?? 0),
            $rows
        ) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * Approve a review through LINK_INTERNAL_API v1/client/artifact-review/approve { public_id }
     */
    public function approve(): ResponseInterface
    {
        return $this->setStatus('v1/client/artifact-review/approve');
    }

    /**
     * Hide a review through LINK_INTERNAL_API v1/client/artifact-review/hide { public_id }
     */
    public function hide(): ResponseInterface
    {
        return $this->setStatus('v1/client/artifact-review/hide');
    }

    /**
     * approve() and hide()
     */
    private function setStatus(string $endpoint): ResponseInterface
    {
        $publicId = trim((string) ($this->request->getJsonVar('public_id') ?? $this->request->getPost('public_id')));
        [$code, $body] = $this->callApi($endpoint, ['public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setStatusCode($code === 200 ? 502 : $code)->setJSON([
                'success'   => false,
                'message'   => $body['message'] ?? lang('Reviews.action-failed'),
                'csrf_hash' => csrf_hash(),
            ]);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? '',
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Table Builder config of index(): no edit column, no Add button; the API gives the order (newest first) and
     * has no search, so neither is offered
     */
    private function listConfig(): array
    {
        $statuses = lang('Reviews.enum.review_status');
        $column   = static fn (string $data, array $more = []) => ['data' => $data, 'title' => lang('Reviews.column.' . $data),
            'orderable' => false, 'searchable' => false] + $more;
        return [
            'table_id'   => 'reviews-table',
            'title'      => lang('Reviews.title'),
            'subtitle'   => lang('Reviews.subtitle', [(string) currentBusinessName()]),
            'id_field'   => 'public_id',
            'editable'   => false,
            'urls'       => ['data' => base_url('admin/reviews/data')],
            'messages'   => ['loadFailed' => lang('Reviews.load-failed')],
            'columns'    => [
                $column('created_at', ['type' => 'datetime', 'class' => 'text-nowrap']),
                $column('artifact_name'),
                $column('customer_name'),
                $column('rating', ['type' => 'html', 'class' => 'text-nowrap']),
                $column('review', ['type' => 'html', 'width' => '35%']),
                $column('review_status', ['type' => 'badge', 'badges' => [
                    self::STATUS_PENDING  => ['label' => $statuses[self::STATUS_PENDING], 'class' => 'bg-warning'],
                    self::STATUS_APPROVED => ['label' => $statuses[self::STATUS_APPROVED], 'class' => 'bg-success'],
                    self::STATUS_HIDDEN   => ['label' => $statuses[self::STATUS_HIDDEN], 'class' => 'bg-secondary'],
                ]]),
                $column('actions', ['type' => 'html', 'class' => 'text-end text-nowrap']),
            ],
            'filters'    => [
                ['name' => 'review_status', 'type' => 'select', 'label' => lang('Reviews.filter.review_status'), 'options' => $statuses],
            ],
            'order'      => [],
            'dt_options' => ['searching' => false, 'ordering' => false],
        ];
    }

    /**
     * The rating as stars, escaped here as the column is inserted as HTML
     */
    private static function stars(int $rating): string
    {
        $rating = max(0, min(self::MAX_RATING, $rating));
        return '<span class="text-warning" title="' . $rating . '/' . self::MAX_RATING . '">'
            . str_repeat('<i class="bi bi-star-fill"></i>', $rating) . str_repeat('<i class="bi bi-star"></i>', self::MAX_RATING - $rating)
            . '</span><span class="visually-hidden">' . $rating . '/' . self::MAX_RATING . '</span>';
    }

    /**
     * The review's title, message and the business's reply, escaped here as the column is inserted as HTML
     */
    private static function reviewHtml(array $review): string
    {
        $html  = '<div class="fw-semibold">' . esc((string) ($review['review_title'] ?? '')) . '</div>';
        $html .= '<div class="small" style="white-space: pre-line;">' . esc((string) ($review['review_message'] ?? '')) . '</div>';
        $reply = trim((string) ($review['reply_message'] ?? ''));
        if ('' !== $reply) {
            $html .= '<div class="small text-muted border-start ps-2 mt-1" style="white-space: pre-line;"><strong>' . esc(lang('Reviews.replied')) . '</strong> ' . esc($reply) . '</div>';
        }
        return $html;
    }

    /**
     * The Actions cell: Approve unless approved already, Hide unless hidden already
     */
    private function actions(string $publicId, string $status): string
    {
        $button = static fn (string $action, string $class, string $icon, string $label) => '<button type="button" class="btn btn-sm ' . $class
            . ' ms-1" data-review-action="' . $action . '" data-review-id="' . esc($publicId, 'attr') . '"><i class="bi ' . $icon . '"></i> '
            . esc($label) . '</button>';
        $html = '';
        if (self::STATUS_APPROVED !== $status) {
            $html .= $button('approve', 'btn-outline-success', 'bi-check-lg', lang('Reviews.approve'));
        }
        if (self::STATUS_HIDDEN !== $status) {
            $html .= $button('hide', 'btn-outline-secondary', 'bi-eye-slash', lang('Reviews.hide'));
        }
        return $html;
    }
}
