<?php

namespace App\Console\Commands;

use App\Support\Security\RouteAuthorizationAuditor;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class SecurityRouteAuditCommand extends Command
{
    protected $signature = 'security:route-audit
        {--strict : Return non-zero exit code if any route is classified as failing}
        {--json : Emit machine-readable JSON summary}';

    protected $description = 'Audit registered routes for explicit authorization middleware or documented service authorization';

    public function handle(RouteAuthorizationAuditor $auditor): int
    {
        $result = $auditor->audit();

        if ($this->option('json')) {
            $payload = [
                'total' => $result['total'],
                'counts' => $result['counts'],
                'failures' => $result['failures'],
                'allowlisted' => $result['allowlisted'],
                'public_allowlisted' => $result['public_allowlisted'],
            ];

            $this->output->write(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

            if ($this->option('strict') && $result['counts']['failing'] > 0) {
                return SymfonyCommand::FAILURE;
            }

            return SymfonyCommand::SUCCESS;
        }

        $this->info('Mini ERP - Route Authorization Audit');
        $this->line(sprintf('Total routes scanned: %d', $result['total']));
        $this->newLine();

        $this->table(
            ['Category', 'Count'],
            [
                ['Explicitly Authorized', $result['counts']['explicitly_authorized']],
                ['Service Authorized (Allowlisted)', $result['counts']['service_authorized_allowlist']],
                ['Public', $result['counts']['public']],
                ['Guest', $result['counts']['guest']],
                ['Failing', $result['counts']['failing']],
            ],
        );

        $this->newLine();
        $this->info('Public Allowlisted Routes:');
        $this->table(
            ['Route Name', 'URI', 'Methods', 'Reason'],
            array_map(
                static fn (array $item): array => [
                    $item['name'] ?? '(unnamed)',
                    '/'.$item['uri'],
                    implode('|', $item['methods']),
                    $item['reason'],
                ],
                $result['public_allowlisted'],
            ),
        );

        $this->newLine();
        $this->info('Service-Authorized Allowlisted Routes:');
        $this->table(
            ['Route Name', 'URI', 'Methods', 'Authorization Reason'],
            array_map(
                static fn (array $item): array => [
                    $item['name'],
                    '/'.$item['uri'],
                    implode('|', $item['methods']),
                    $item['reason'],
                ],
                $result['allowlisted'],
            ),
        );

        if ($result['counts']['failing'] > 0) {
            $this->newLine();
            $this->error(sprintf('Found %d failing route(s) lacking authorization middleware:', $result['counts']['failing']));
            $this->table(
                ['Route Name', 'URI', 'Methods', 'Middleware'],
                array_map(
                    static fn (array $item): array => [
                        $item['name'] ?? '(unnamed)',
                        '/'.$item['uri'],
                        implode('|', $item['methods']),
                        implode(', ', $item['middleware']),
                    ],
                    $result['failures'],
                ),
            );

            if ($this->option('strict')) {
                return SymfonyCommand::FAILURE;
            }
        } else {
            $this->newLine();
            $this->info('All protected routes satisfy authorization requirements.');
        }

        return SymfonyCommand::SUCCESS;
    }
}
