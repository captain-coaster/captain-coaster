<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Regression test for the whole-branch review finding: NoBlockedWordsValidator
 * logs at "info" level, but the "main" handler in both when@prod and when@test
 * is a fingers_crossed handler with action_level: error and no
 * passthru_level — meaning an info record is buffered and silently dropped
 * unless an error happens in the same request. These assertions can't run a
 * real kernel/monolog stack without much heavier test infrastructure than
 * this project currently has (no WebTestCase/KernelTestCase usage exists),
 * so this is a config-level assertion that the "moderation" channel has its
 * own non-buffering handler and is excluded from the fingers_crossed "main"
 * handler in both environments.
 */
class MonologConfigTest extends TestCase
{
    private const string CONFIG_PATH = __DIR__.'/../../config/packages/monolog.yaml';

    /** @return array<string, mixed> */
    private function parsedConfig(): array
    {
        return Yaml::parseFile(self::CONFIG_PATH);
    }

    public function testModerationChannelIsDeclared(): void
    {
        $config = $this->parsedConfig();

        $this->assertContains('moderation', $config['monolog']['channels']);
    }

    /** @return iterable<string, array{0: string}> */
    public static function environmentProvider(): iterable
    {
        yield 'test' => ['when@test'];
        yield 'prod' => ['when@prod'];
    }

    /** @dataProvider environmentProvider */
    public function testMainFingersCrossedHandlerExcludesModerationChannel(string $envKey): void
    {
        $config = $this->parsedConfig();
        $main = $config[$envKey]['monolog']['handlers']['main'];

        $this->assertSame('fingers_crossed', $main['type']);
        $this->assertContains('!moderation', $main['channels']);
    }

    /** @dataProvider environmentProvider */
    public function testModerationHandlerWritesInfoLevelOutsideFingersCrossedBuffer(string $envKey): void
    {
        $config = $this->parsedConfig();
        $handlers = $config[$envKey]['monolog']['handlers'];

        $this->assertArrayHasKey('moderation', $handlers, "Expected a dedicated 'moderation' handler in {$envKey}");

        $moderationHandler = $handlers['moderation'];

        $this->assertNotSame('fingers_crossed', $moderationHandler['type'], 'Moderation handler must not itself be a buffering fingers_crossed handler');
        $this->assertSame('info', $moderationHandler['level']);
        $this->assertSame(['moderation'], $moderationHandler['channels']);
    }
}
