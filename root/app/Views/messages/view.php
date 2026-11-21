<?php
/**
 * One message to the session business; see App\Controllers\Messages::message(). Opening it marked it read for
 * everyone at the business if nobody had. The customer is answered off the platform (the email and telephone links);
 * the status buttons and the comments are run by scripts/message_view, which posts to admin/messages/* and reloads.
 * @var array  $message  v1/client/message's message
 * @var array  $timeline [{public_id, event_type (C comment, S status), event_text, from_status, to_status, author_name, is_own, created_at}], oldest first
 * @var bool   $can_reply Whether the business's plan includes replying from here: not yet for any
 * @var string $business_name
 * @var array  $badges   Messages::STATUS_BADGES
 */
$this->extend('_layout_admin');
$statuses   = (array) lang('Messages.status');
$status     = (string) ($message['message_status'] ?? '');
$badge      = static fn (string $code): string => '<span class="badge ' . esc($badges[$code] ?? 'bg-secondary', 'attr') . '">' . esc((string) ($statuses[$code] ?? $code)) . '</span>';
$publicId   = (string) ($message['public_id'] ?? '');
$email      = (string) ($message['email_address'] ?? '');
$phone      = (string) ($message['phone_number'] ?? '');
$maxComment = 2000; // BusinessMessageModel::COMMENT_MAX_LENGTH of the API
helper('list');
$languages  = listLanguages(); // the language the customer wrote in, the one to answer in
$replyLink  = $email === '' ? '' : 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode(lang('Messages.view.reply-subject', [$business_name]));
?>
<?= $this->section('content') ?>
<section class="section" id="message-view">
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <h5 class="card-title mb-0"><?= esc((string) ($message['sender_name'] ?? '')) ?></h5>
                            <div class="small text-muted"><?= esc(lang('Messages.view.received')) ?> <?= localTimeTag($message['created_at'] ?? null) ?>
                                · <?= esc((string) ($languages[$message['locale'] ?? ''] ?? $message['locale'] ?? '')) ?></div>
                        </div>
                        <?= $badge($status) ?>
                    </div>
                    <?php if ($status === 'S' && ! empty($message['spam_reason'])) : ?>
                        <div class="alert alert-secondary small py-2"><?= esc(lang('Messages.view.spam-note')) ?></div>
                    <?php endif; ?>
                    <div class="border rounded p-3 mb-3" style="white-space: pre-line;"><?= esc((string) ($message['message_text'] ?? '')) ?></div>
                    <dl class="row mb-3">
                        <?php if ($email !== '') : ?>
                            <dt class="col-sm-4"><?= esc(lang('Messages.view.email')) ?></dt>
                            <dd class="col-sm-8 text-break"><a href="mailto:<?= esc($email, 'attr') ?>"><?= esc($email) ?></a></dd>
                        <?php endif; ?>
                        <?php if ($phone !== '') : ?>
                            <dt class="col-sm-4"><?= esc(lang('Messages.view.phone')) ?></dt>
                            <dd class="col-sm-8"><a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $phone), 'attr') ?>"><?= esc($phone) ?></a></dd>
                        <?php endif; ?>
                        <dt class="col-sm-4"><?= esc(lang('Messages.view.first-read')) ?></dt>
                        <dd class="col-sm-8"><?= esc((string) ($message['first_read_by_name'] ?? '')) ?> <?= localTimeTag($message['first_read_at'] ?? null) ?></dd>
                        <?php if (! empty($message['resolved_at'])) : ?>
                            <dt class="col-sm-4"><?= esc(lang('Messages.view.resolved')) ?></dt>
                            <dd class="col-sm-8 mb-0"><?= esc((string) ($message['resolved_by_name'] ?? '')) ?> <?= localTimeTag($message['resolved_at']) ?></dd>
                        <?php endif; ?>
                    </dl>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($status === 'N' || $status === 'R') : ?>
                            <button type="button" class="btn btn-success" data-message-status="D"><i class="bi bi-check2-circle"></i> <?= esc(lang('Messages.action.resolve')) ?></button>
                        <?php endif; ?>
                        <?php if ($status === 'D') : ?>
                            <button type="button" class="btn btn-outline-primary" data-message-status="R"><i class="bi bi-arrow-counterclockwise"></i> <?= esc(lang('Messages.action.reopen')) ?></button>
                        <?php endif; ?>
                        <?php if ($status === 'R') : ?>
                            <button type="button" class="btn btn-outline-secondary" data-message-status="N" data-leave="1"><i class="bi bi-envelope"></i> <?= esc(lang('Messages.action.unread')) ?></button>
                        <?php endif; ?>
                        <?php if ($status === 'S') : ?>
                            <button type="button" class="btn btn-outline-primary" data-message-status="R"><i class="bi bi-envelope-check"></i> <?= esc(lang('Messages.action.not-spam')) ?></button>
                        <?php else : ?>
                            <button type="button" class="btn btn-outline-danger ms-auto" data-message-status="S" data-confirm="1"><i class="bi bi-slash-circle"></i> <?= esc(lang('Messages.action.spam')) ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-1"><?= esc(lang('Messages.reply.title')) ?></h5>
                    <?php if ($can_reply) : ?>
                        <?php /* replying from the platform: a paid feature to come, posting to v1/client/message/reply */ ?>
                    <?php else : ?>
                        <p class="small text-muted mb-3"><?= esc(lang('Messages.reply.off-platform')) ?></p>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($replyLink !== '') : ?>
                                <a class="btn btn-primary" href="<?= esc($replyLink, 'attr') ?>"><i class="bi bi-reply"></i> <?= esc(lang('Messages.reply.by-email')) ?></a>
                            <?php endif; ?>
                            <?php if ($phone !== '') : ?>
                                <a class="btn btn-outline-primary" href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $phone), 'attr') ?>"><i class="bi bi-telephone"></i> <?= esc(lang('Messages.reply.by-phone')) ?></a>
                            <?php endif; ?>
                        </div>
                        <p class="small text-muted mt-3 mb-0"><i class="bi bi-stars"></i> <?= esc(lang('Messages.reply.coming')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-1"><?= esc(lang('Messages.timeline.title')) ?></h5>
                    <p class="small text-muted"><?= esc(lang('Messages.timeline.subtitle')) ?></p>
                    <?php if ($timeline === []) : ?>
                        <p class="text-muted small"><?= esc(lang('Messages.timeline.none')) ?></p>
                    <?php else : ?>
                        <ul class="list-group list-group-flush mb-3" id="message-timeline">
                            <?php foreach ($timeline as $event) :
                                $author = (string) ($event['author_name'] ?? '') ?: lang('Messages.timeline.unknown'); ?>
                                <li class="list-group-item px-0">
                                    <?php if (($event['event_type'] ?? '') === 'C') : ?>
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div class="small text-muted"><i class="bi bi-chat-left-text"></i> <strong><?= esc($author) ?></strong> · <?= localTimeTag($event['created_at'] ?? null) ?></div>
                                            <?php if (! empty($event['is_own'])) : ?>
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-delete-comment="<?= esc((string) $event['public_id'], 'attr') ?>">
                                                    <i class="bi bi-trash"></i> <?= esc(lang('Messages.timeline.delete')) ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-1" style="white-space: pre-line;"><?= esc((string) ($event['event_text'] ?? '')) ?></div>
                                    <?php elseif (($event['event_type'] ?? '') === 'S') : ?>
                                        <div class="small text-muted"><i class="bi bi-arrow-right-circle"></i>
                                            <?= esc(lang('Messages.timeline.status-' . ($event['to_status'] ?? ''), [$author])) ?> · <?= localTimeTag($event['created_at'] ?? null) ?></div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form id="message-comment-form" novalidate>
                        <textarea class="form-control" name="text" rows="3" maxlength="<?= $maxComment ?>" required
                                  placeholder="<?= esc(lang('Messages.timeline.placeholder'), 'attr') ?>"></textarea>
                        <div class="invalid-feedback" data-error-for="text"></div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="form-text"><?= esc(lang('Messages.timeline.max', [$maxComment])) ?></span>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> <?= esc(lang('Messages.timeline.add')) ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
<?= view('scripts/message_view', [
    'messagePublicId' => $publicId,
    'urls'            => [
        'status' => base_url('admin/messages/status'),
        'add'    => base_url('admin/messages/comment/add'),
        'delete' => base_url('admin/messages/comment/delete'),
        'list'   => base_url('admin/messages'),
    ],
]) ?>
</script>
<?= $this->endSection() ?>
