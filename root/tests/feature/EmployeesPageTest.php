<?php

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;

/**
 * The people of the session business (admin/employees/*): its owners only, through v1/client/employees, employee
 * and employee/edit (stubbed here)
 *
 * @internal
 */
final class EmployeesPageTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const USER     = ['id' => 1, 'uid' => 'owneruid', 'email_address' => 'owner@example.com', 'full_name' => 'Olive Owner',
        'avatar_path' => null, 'display_name' => null, 'preferred_language' => 'en-US'];
    private const BUSINESS = ['business_slug' => 'otter-cafe', 'business_name' => 'Otter Cafe', 'country_code' => 'TH', 'currency_code' => 'THB'];
    private const LIST     = ['employees' => [
        ['uid' => 'owneruid', 'email_address' => 'owner@example.com', 'telephone_number' => null, 'full_name' => 'Olive Owner',
            'account_status' => 'A', 'client_role' => 'O', 'client_status' => 'A', 'start_date' => '2026-01-01', 'termination_date' => null],
        ['uid' => 'emmauid', 'email_address' => 'emma@example.com', 'telephone_number' => '+66812345678', 'full_name' => 'Emma Employee',
            'account_status' => 'S', 'client_role' => 'E', 'client_status' => 'T', 'start_date' => '2026-01-01', 'termination_date' => '2026-02-01'],
    ], 'plan_code' => 'free', 'max_employees' => 1, 'active_employees' => 1, 'can_add' => false];
    private const EMPLOYEE = [
        'account'        => ['uid' => 'emmauid', 'email_address' => 'emma@example.com', 'telephone_number' => '+66812345678',
            'password_expiry' => '2027-01-01', 'full_name' => 'Emma Employee', 'otp_mode' => 'E', 'account_status' => 'S', 'account_type' => 'C', 'avatar_path' => null],
        'profile'        => ['legal_first_name' => 'Emma', 'legal_middle_name' => null, 'legal_last_name' => 'Otter', 'display_name' => 'Em',
            'national_id_type' => 'P', 'national_id_number' => 'AB1234567'],
        'link'           => ['client_role' => 'E', 'client_status' => 'A', 'start_date' => '2026-01-01', 'termination_date' => null, 'owner_notes' => 'Keys holder'],
        'status_options' => ['S', 'A'],
        'is_self'        => false,
        'can_leave'      => false,
        'rehire'         => null,
    ];

    /** @var list<array{string, array}> endpoints and payloads sent to the API */
    private array $calls = [];

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    private function stubApi(array ...$replies): void
    {
        $curl = $this->createMock(CURLRequest::class);
        $curl->method('post')->willReturnCallback(function (string $url, array $options) use (&$replies) {
            $this->calls[] = [substr($url, strlen((string) getenv('LINK_INTERNAL_API'))), $options['json'] ?? []];
            [$code, $body] = array_shift($replies);
            return (new Response(new App()))->setStatusCode($code)->setBody(json_encode($body));
        });
        Services::injectMock('curlrequest', $curl);
    }

    private function session(string $role): array
    {
        return ['access_token' => 'test-token', 'expires_at' => time() + 3600, 'user' => self::USER,
            'business' => ['client_role' => $role, 'client_status' => 'A', 'is_default_business' => 'Y', 'business' => self::BUSINESS]];
    }

    public function testOnlyOwnersGetIn(): void
    {
        foreach (['M', 'E'] as $role) {
            foreach (['admin/employees', 'admin/employees/create', 'admin/employees/edit/emmauid'] as $page) {
                $this->withSession($this->session($role))->get($page)->assertRedirectTo(base_url('admin/dashboard'));
            }
            $this->withSession($this->session($role))->post('admin/employees/save', ['uid' => 'emmauid'])->assertStatus(403);
            $this->withSession($this->session($role))->post('admin/employees/terminate', ['uid' => 'emmauid', 'termination_date' => '2026-03-01'])->assertStatus(403);
            $this->withSession($this->session($role))->post('admin/employees/rehire', ['uid' => 'emmauid'])->assertStatus(403);
            foreach (['find', 'add', 'invite'] as $path) {
                $this->withSession($this->session($role))->post('admin/employees/' . $path, ['email_address' => 'emma@example.com'])->assertStatus(403);
            }
            $this->withSession($this->session($role))->get('admin/dashboard')->assertDontSee('admin/employees');
        }
        $this->assertSame([], $this->calls);
    }

    public function testTheListDisablesAddAtThePlanCap(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::LIST]]);

        $result = $this->withSession($this->session('O'))->get('admin/employees');

        $result->assertOK();
        $result->assertSee('Add New Employee');
        $result->assertSee('The Free plan allows up to 1 active employees');
        $this->assertMatchesRegularExpression('#<button type="button" class="btn btn-outline-primary" disabled#', $result->getBody());
        $this->assertSame([['v1/client/employees', ['business_slug' => 'otter-cafe']]], $this->calls);
    }

    public function testTheListDataHasEveryoneWithTheirEditLinks(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::LIST]]);

        $result = $this->withSession($this->session('O'))->post('admin/employees/data', ['draw' => 1, 'start' => 0, 'length' => 25]);

        $result->assertOK();
        $body = json_decode($result->getJSON(), true);
        $this->assertSame(2, $body['recordsTotal']);
        $this->assertSame(['Emma Employee', 'Olive Owner'], array_column($body['data'], 'full_name'));
        $this->assertSame(['S', 'A'], array_column($body['data'], 'account_status'));
        $this->assertSame(['T', 'A'], array_column($body['data'], 'client_status'));
        $this->assertStringEndsWith('admin/employees/edit/emmauid', $body['data'][0]['edit_url']);
        $this->assertNotSame('', $body['data'][0]['telephone_number']);
    }

    public function testTheFormShowsWhatTheOwnerMaySeeAndLocksTheRest(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::EMPLOYEE]]);

        $result = $this->withSession($this->session('O'))->get('admin/employees/edit/emmauid');

        $result->assertOK();
        $html = $result->getBody();
        $this->assertSame([['v1/client/employee', ['business_slug' => 'otter-cafe', 'uid' => 'emmauid']]], $this->calls);
        foreach (['email_address', 'password_expiry', 'otp_mode', 'account_type', 'client_role', 'client_status', 'start_date'] as $locked) {
            $this->assertMatchesRegularExpression('#<(input|select)[^>]*name="' . $locked . '"[^>]*disabled#', $html, $locked);
        }
        foreach (['full_name', 'account_status', 'legal_first_name', 'display_name', 'national_id_type', 'national_id_number', 'owner_notes'] as $editable) {
            $this->assertDoesNotMatchRegularExpression('#<(input|select|textarea)[^>]*name="' . $editable . '"[^>]*disabled#', $html, $editable);
        }
        // a suspended account: only Suspended (current) and Active can be picked
        $this->assertMatchesRegularExpression('#<option value="B" disabled#', $html);
        $this->assertMatchesRegularExpression('#<option value="P" disabled#', $html);
        $this->assertDoesNotMatchRegularExpression('#<option value="A" disabled#', $html);
        $this->assertDoesNotMatchRegularExpression('#<option value="S" disabled#', $html);
        $result->assertSee('AB1234567');
        $result->assertSee('Keys holder');
        foreach (['admin_notes', 'internal_notes', 'gender', 'date_of_birth', 'nationality_code', 'status_message', 'status_emoji', 'preferred_language', 'name="bio"', 'marketing_opt_in'] as $hidden) {
            $result->assertDontSee($hidden);
        }
    }

    public function testAnActiveAccountCannotBeSuspended(): void
    {
        $active = ['account' => ['account_status' => 'A'] + self::EMPLOYEE['account'], 'status_options' => ['A']] + self::EMPLOYEE;
        $this->stubApi([200, ['status' => 'success', 'data' => $active]]);

        $html = $this->withSession($this->session('O'))->get('admin/employees/edit/emmauid')->getBody();

        $this->assertMatchesRegularExpression('#<select[^>]*name="account_status"[^>]*disabled#', $html);
        $this->assertStringContainsString('end their employment instead', $html);
    }

    public function testSomeoneStillWorkingHereCanBeLetGo(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::EMPLOYEE]]);

        $result = $this->withSession($this->session('O'))->get('admin/employees/edit/emmauid');

        $result->assertSee('id="terminate-form"');
        $html = $result->getBody();
        $this->assertMatchesRegularExpression('#name="termination_date"[^>]*min="2026-01-01"#', $html);
        $this->assertMatchesRegularExpression('#name="termination_date"[^>]*max="' . date('Y-m-d') . '"#', $html);
        $result->assertSee('Emma Employee will no longer work at this business from {0}.');
    }

    public function testTheOnlyOwnerCannotLeave(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['is_self' => true, 'status_options' => ['S']] + self::EMPLOYEE]]);

        $result = $this->withSession($this->session('O'))->get('admin/employees/edit/owneruid');
        $result->assertDontSee('terminate-form');
        $result->assertSee('You are the only active owner of this business');
    }

    public function testAnOwnerCanLeaveWhenAnotherOwnerCanTakeOver(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['is_self' => true, 'can_leave' => true, 'status_options' => ['S']] + self::EMPLOYEE]]);

        $result = $this->withSession($this->session('O'))->get('admin/employees/edit/owneruid');
        $result->assertSee('id="terminate-form"');
        $result->assertSee('Leave Business');
        $result->assertSee('You will no longer work at Otter Cafe from {0}');
    }

    public function testLeavingMovesTheSessionToAnotherBusiness(): void
    {
        $other = ['business_slug' => 'otter-bar', 'business_name' => 'Otter Bar'];
        $this->stubApi([200, ['status' => 'success', 'data' => ['uid' => 'owneruid']]],
            [200, ['status' => 'success', 'data' => ['businesses' => [$other + ['client_role' => 'E', 'is_default_business' => 'Y']]]]],
            [200, ['status' => 'success', 'data' => ['business' => ['client_role' => 'E', 'client_status' => 'A', 'is_default_business' => 'Y', 'business' => $other]]]]);

        $result = $this->withSession($this->session('O'))->post('admin/employees/terminate', ['uid' => 'owneruid', 'termination_date' => '2026-03-15']);

        $result->assertOK();
        $this->assertSame(['v1/client/employee/terminate', 'v1/client/businesses', 'v1/client/business/switch'], array_column($this->calls, 0));
        $this->assertSame(['business_slug' => 'otter-bar'], $this->calls[2][1]);
        $this->assertSame('otter-bar', $_SESSION['business']['business']['business_slug']);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('You have left Otter Cafe.', $body['message']);
        $this->assertStringEndsWith('admin/dashboard', $body['redirect']);
    }

    public function testLeavingTheLastBusinessLeavesNone(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['uid' => 'owneruid']]], [200, ['status' => 'success', 'data' => ['businesses' => []]]]);

        $this->withSession($this->session('O'))->post('admin/employees/terminate', ['uid' => 'owneruid', 'termination_date' => '2026-03-15'])->assertOK();

        $this->assertArrayHasKey('business', $_SESSION);
        $this->assertNull($_SESSION['business']); // AuthFilter now sends them to create a business
    }

    private function gone(bool $room): array
    {
        $gone = ['link' => ['client_status' => 'T', 'termination_date' => '2026-02-01'] + self::EMPLOYEE['link']] + self::EMPLOYEE;

        return ['rehire' => ['plan_code' => 'free', 'max_employees' => $room ? 2 : 1, 'can_add' => $room]] + $gone;
    }

    public function testSomeoneGoneCanBeRehired(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => $this->gone(true)]], [200, ['status' => 'success', 'data' => ['uid' => 'emmauid'], 'message' => 'Rehired.']]);

        $result = $this->withSession($this->session('O'))->get('admin/employees/edit/emmauid');
        $result->assertDontSee('terminate-form');
        $result->assertSee('id="rehire-form"');
        $result->assertSee('Emma Employee will work at this business again');

        $result = $this->withSession($this->session('O'))->post('admin/employees/rehire', ['uid' => 'emmauid']);
        $result->assertOK();
        $this->assertSame(['v1/client/employee/rehire', ['business_slug' => 'otter-cafe', 'uid' => 'emmauid']], $this->calls[1]);
        $this->assertStringEndsWith('admin/employees/edit/emmauid', json_decode($result->getJSON(), true)['redirect']);
    }

    public function testNoRehireOverThePlanCap(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => $this->gone(false)]]);

        $result = $this->withSession($this->session('O'))->get('admin/employees/edit/emmauid');
        $result->assertDontSee('rehire-form');
        $result->assertSee('The Free plan allows up to 1 active employees');
    }

    public function testLockedFieldsAreNotMarkedOptional(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::EMPLOYEE]]);

        $html = $this->withSession($this->session('O'))->get('admin/employees/edit/emmauid')->getBody();

        // locked: telephone (optional in the model) has no marker; editable optional fields keep it
        $this->assertDoesNotMatchRegularExpression('#<label for="telephone_number[^"]*"[^>]*>[^<]*<span class="form-label-optional">#', $html);
        $this->assertMatchesRegularExpression('#<label for="legal_middle_name"[^>]*>[^<]*<span class="form-label-optional">#', $html);
    }

    public function testTerminateForwardsTheDate(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['uid' => 'emmauid'], 'message' => 'Employment ended.']],
            [422, ['status' => 'error', 'message' => 'Please correct the highlighted fields.', 'errors' => ['termination_date' => 'The termination date cannot be in the future.']]]);

        $result = $this->withSession($this->session('O'))->post('admin/employees/terminate', ['uid' => 'emmauid', 'termination_date' => ' 2026-03-15 ']);

        $result->assertOK();
        $this->assertSame(['v1/client/employee/terminate', ['business_slug' => 'otter-cafe', 'uid' => 'emmauid', 'termination_date' => '2026-03-15']], $this->calls[0]);
        $this->assertStringEndsWith('admin/employees/edit/emmauid', json_decode($result->getJSON(), true)['redirect']);

        $result = $this->withSession($this->session('O'))->post('admin/employees/terminate', ['uid' => 'emmauid', 'termination_date' => '2099-01-01']);
        $result->assertStatus(422);
        $this->assertSame(['termination_date'], array_keys(json_decode($result->getJSON(), true)['errors']));
    }

    public function testTheAddPageFindsThenAddsOrInvites(): void
    {
        $list = ['invitations' => [['email_address' => 'new@example.com', 'client_role' => 'M', 'expires_at' => 1893456000]], 'can_add' => true] + self::LIST;
        $this->stubApi([200, ['status' => 'success', 'data' => $list]]);

        $result = $this->withSession($this->session('O'))->get('admin/employees/create');

        $result->assertOK();
        foreach (['find-employee-form', 'add-employee-form', 'invite-employee-form'] as $form) {
            $result->assertSee('id="' . $form . '"');
        }
        $result->assertSee('Open Invitations');
        $result->assertSee('new@example.com');
        $this->assertMatchesRegularExpression('#<option value="E" selected#', $result->getBody()); // Employee at first
    }

    public function testTheAddPageGoesBackToTheListAtThePlanCap(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => self::LIST]]); // can_add false

        $this->withSession($this->session('O'))->get('admin/employees/create')->assertRedirectTo(base_url('admin/employees'));
    }

    public function testFindAnswersWhoTheAddressBelongsTo(): void
    {
        $this->stubApi(
            [200, ['status' => 'success', 'data' => ['found' => true, 'account' => ['uid' => 'emmauid', 'email_address' => 'emma@example.com', 'full_name' => 'Emma', 'avatar_path' => null], 'client_status' => null]]],
            [200, ['status' => 'success', 'data' => ['found' => true, 'account' => ['uid' => 'fred uid', 'email_address' => 'fred@example.com', 'full_name' => 'Fred', 'avatar_path' => null], 'client_status' => 'T']]],
            [200, ['status' => 'success', 'data' => ['found' => false]]],
        );

        $emma = json_decode($this->withSession($this->session('O'))->post('admin/employees/find', ['email_address' => ' emma@example.com '])->getJSON(), true);
        $fred = json_decode($this->withSession($this->session('O'))->post('admin/employees/find', ['email_address' => 'fred@example.com'])->getJSON(), true);
        $none = json_decode($this->withSession($this->session('O'))->post('admin/employees/find', ['email_address' => 'new@example.com'])->getJSON(), true);

        $this->assertSame(['v1/client/employee/find', ['business_slug' => 'otter-cafe', 'email_address' => 'emma@example.com']], $this->calls[0]);
        $this->assertSame([true, 'Emma', null, null], [$emma['found'], $emma['account']['full_name'], $emma['client_status'], $emma['url']]);
        $this->assertStringEndsWith('admin/employees/edit/fred%20uid', $fred['url']);
        $this->assertSame([false, null], [$none['found'], $none['account']]);
    }

    public function testAddAndInviteForwardTheRoleAndNotes(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['uid' => 'emmauid'], 'message' => 'Emma now works at this business.']],
            [200, ['status' => 'success', 'data' => ['email_address' => 'new@example.com'], 'message' => 'We have emailed an invitation to new@example.com.']]);
        $form = ['email_address' => 'emma@example.com', 'client_role' => 'M', 'owner_notes' => 'Mondays', 'client_status' => 'A', 'start_date' => '2020-01-01'];

        $added   = json_decode($this->withSession($this->session('O'))->post('admin/employees/add', $form)->getJSON(), true);
        $invited = json_decode($this->withSession($this->session('O'))->post('admin/employees/invite', ['email_address' => 'new@example.com'] + $form)->getJSON(), true);

        $expected = ['business_slug' => 'otter-cafe', 'email_address' => 'emma@example.com', 'client_role' => 'M', 'owner_notes' => 'Mondays'];
        $this->assertSame(['v1/client/employee/add', $expected], $this->calls[0]);
        $this->assertEquals(['email_address' => 'new@example.com'] + $expected, $this->calls[1][1]); // order aside
        $this->assertSame('v1/client/employee/invite', $this->calls[1][0]);
        $this->assertStringEndsWith('admin/employees/edit/emmauid', $added['redirect']);
        $this->assertStringEndsWith('admin/employees/create', $invited['redirect']);
    }

    public function testTheOwnersOwnStatusIsLocked(): void
    {
        $self = ['status_options' => ['A'], 'is_self' => true] + self::EMPLOYEE;
        $this->stubApi([200, ['status' => 'success', 'data' => $self]]);

        $html = $this->withSession($this->session('O'))->get('admin/employees/edit/owneruid')->getBody();

        $this->assertMatchesRegularExpression('#<select[^>]*name="account_status"[^>]*disabled#', $html);
        $this->assertStringContainsString('You cannot change the status of your own account.', $html);
    }

    public function testSaveForwardsOnlyTheEditableFields(): void
    {
        $this->stubApi([200, ['status' => 'success', 'data' => ['uid' => 'emmauid'], 'message' => 'Employee saved.']]);

        $result = $this->withSession($this->session('O'))->post('admin/employees/save', [
            'uid' => 'emmauid', 'full_name' => 'Emma E.', 'account_status' => 'S', 'email_address' => 'x@example.com', 'telephone_number' => '+6620000000',
            'legal_first_name' => 'Emma', 'national_id_number' => 'AB1', 'internal_notes' => 'x', 'owner_notes' => 'n', 'gender' => 'F', 'client_role' => 'O',
        ]);

        $result->assertOK();
        [$endpoint, $payload] = $this->calls[0];
        $this->assertSame('v1/client/employee/edit', $endpoint);
        $this->assertSame(['business_slug' => 'otter-cafe', 'uid' => 'emmauid'], array_slice($payload, 0, 2));
        $this->assertSame(['full_name' => 'Emma E.', 'account_status' => 'S'], (array) $payload['account']);
        $this->assertSame(['legal_first_name' => 'Emma', 'national_id_number' => 'AB1'], (array) $payload['profile']);
        $this->assertSame(['owner_notes' => 'n'], (array) $payload['link']);
    }

    public function testAnUnknownEmployeeIsNotFound(): void
    {
        $this->stubApi([404, ['status' => 'error', 'message' => 'Employee not found.']]);
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->withSession($this->session('O'))->get('admin/employees/edit/nobody');
    }
}
