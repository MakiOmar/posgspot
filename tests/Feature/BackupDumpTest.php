<?php

namespace Tests\Feature;

use App\Console\Commands\RunAutomaticBackup;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Tests\TestCase;

/**
 * mysqldump TLS toggle and the failure reason shown on the Backup page.
 */
class BackupDumpTest extends TestCase
{
    private function credentialsFor(array $dumpConfig): string
    {
        config()->set('database.connections.mysql.dump', $dumpConfig);

        return DbDumperFactory::createFromConnection('mysql')->getContentsOfCredentialsFile();
    }

    public function test_skip_ssl_disables_tls_for_mysqldump(): void
    {
        $this->assertMatchesRegularExpression(
            '/^(skip-ssl|ssl-mode=DISABLED)$/m',
            $this->credentialsFor(['useSingleTransaction' => true, 'skip_ssl' => true])
        );
    }

    public function test_dump_keeps_tls_when_flag_is_absent(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/^(skip-ssl|ssl-mode=DISABLED)$/m',
            $this->credentialsFor(['useSingleTransaction' => true])
        );
    }

    public function test_default_config_omits_skip_ssl_and_keeps_single_transaction(): void
    {
        $dump = config('database.connections.mysql.dump');

        $this->assertArrayNotHasKey('skip_ssl', $dump);
        $this->assertTrue($dump['useSingleTransaction']);
    }

    public function test_failure_reason_uses_spatie_message_and_error_output(): void
    {
        $output = <<<'OUT'
Starting backup...
Dumping database ultimate_pos...
Backup failed because: The dump process failed with a none successful exitcode.
Exitcode
========
127: Command not found

Output
======
<no output>

Error Output
============
sh: 1: mysqldump: not found
.
OUT;

        $this->assertSame(
            'The dump process failed with a none successful exitcode — sh: 1: mysqldump: not found',
            RunAutomaticBackup::failureReason($output)
        );
    }

    public function test_failure_reason_falls_back_to_last_line(): void
    {
        $this->assertSame('Disk full', RunAutomaticBackup::failureReason("Starting backup...\nDisk full\n"));
        $this->assertSame('', RunAutomaticBackup::failureReason(''));
    }
}
