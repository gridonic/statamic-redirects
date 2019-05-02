<?php

use Statamic\Addons\Redirects\RedirectsLogger;
use Statamic\API\File;
use Statamic\API\YAML;
use Statamic\Testing\TestCase;

/**
 * @group redirects
 * @coversDefaultClass \Statamic\Addons\Redirects\RedirectsLogger
 */
class RedirectsLoggerTest extends TestCase
{
    /**
     * @var string
     */
    private $storagePath;

    /**
     * @var RedirectsLogger
     */
    private $redirectsLogger;

    public function setUp()
    {
        parent::setUp();

        $this->storagePath = __DIR__ . '/temp/';
        $this->redirectsLogger = new RedirectsLogger($this->storagePath);
    }

    /**
     * @test
     * @covers ::log404
     * @covers ::logAutoRedirect
     * @covers ::logManualRedirect
     * @covers ::flush
     * @covers ::remove404
     */
    public function it_should_store_and_return_logs_correctly()
    {
        $this->redirectsLogger
            ->log404('/404')
            ->logManualRedirect('/manual')
            ->logAutoRedirect('/auto')
            ->flush();

        $this->assertEquals(['/404' => 1], $this->redirectsLogger->get404s());
        $this->assertEquals(['/manual' => 1], $this->redirectsLogger->getManualRedirects());
        $this->assertEquals(['/auto' => 1], $this->redirectsLogger->getAutoRedirects());
        $this->assertEquals(['/404' => 1], $this->getLogsFromYamlFile('404'));
        $this->assertEquals(['/manual' => 1], $this->getLogsFromYamlFile('manual'));
        $this->assertEquals(['/auto' => 1], $this->getLogsFromYamlFile('auto'));

        $this->redirectsLogger
            ->log404('/404')
            ->flush();

        $this->assertEquals(['/404' => 2], $this->redirectsLogger->get404s());
        $this->assertEquals(['/404' => 2], $this->getLogsFromYamlFile('404'));

        $this->redirectsLogger
            ->remove404('/404')
            ->flush();

        $this->assertEmpty($this->redirectsLogger->get404s());
        $this->assertEmpty($this->getLogsFromYamlFile('404'));
    }

    public function tearDown()
    {
        parent::tearDown();

        foreach (['404', 'manual', 'auto'] as $what) {
            unlink($this->storagePath . sprintf('log_%s.yaml', $what));
        }
    }

    private function getLogsFromYamlFile($what)
    {
        return YAML::parse(File::get($this->storagePath . sprintf('log_%s.yaml', $what)));
    }
}
