<?php
/**
 * The open invitations of the session business, below the add-employee forms (generic_form.php's $extraHtml):
 * invited, not signed up yet. Each holds a seat of the plan's cap until it expires.
 *
 * @var list<array{email_address: string, client_role: string, expires_at: int}> $invitations
 * @var array<string, string> $roles client_role => label
 */
?>
<?php if ($invitations !== []): ?>
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0"><?= esc(lang('Employees.invitations.title')) ?></h5>
        <p class="text-muted small mb-0"><?= esc(lang('Employees.invitations.subtitle')) ?></p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th><?= esc(lang('Employees.list.column.email_address')) ?></th>
                    <th><?= esc(lang('Employees.list.column.client_role')) ?></th>
                    <th><?= esc(lang('Employees.invitations.expires')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($invitations as $invitation): ?>
                <tr>
                    <td><?= esc($invitation['email_address']) ?></td>
                    <td><?= esc($roles[$invitation['client_role']] ?? $invitation['client_role']) ?></td>
                    <td><?= localTimeTag(gmdate('Y-m-d H:i:s', (int) $invitation['expires_at'])) ?></td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif ?>
