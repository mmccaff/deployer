<?php

namespace REBELinBLUE\Deployer\Tests\Unit\Jobs\DeployProject;

use Carbon\Carbon;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Support\Collection;
use Mockery as m;
use REBELinBLUE\Deployer\Command;
use REBELinBLUE\Deployer\Deployment;
use REBELinBLUE\Deployer\DeployStep;
use REBELinBLUE\Deployer\Exceptions\CancelledDeploymentException;
use REBELinBLUE\Deployer\Jobs\AbortDeployment;
use REBELinBLUE\Deployer\Jobs\DeployProject\LogFormatter;
use REBELinBLUE\Deployer\Jobs\DeployProject\RunDeploymentStep;
use REBELinBLUE\Deployer\Jobs\DeployProject\ScriptBuilder;
use REBELinBLUE\Deployer\Jobs\DeployProject\SendFileToServer;
use REBELinBLUE\Deployer\Server;
use REBELinBLUE\Deployer\ServerLog;
use REBELinBLUE\Deployer\Services\Filesystem\Filesystem;
use REBELinBLUE\Deployer\Tests\TestCase;

/**
 * @coversDefaultClass \REBELinBLUE\Deployer\Jobs\DeployProject\RunDeploymentStep
 */
class RunDeploymentStepTest extends TestCase
{
    /**
     * @var Deployment
     */
    private $deployment;

    /**
     * @var DeployStep
     */
    private $step;

    /**
     * @var string
     */
    private $private_key;

    /**
     * @var string
     */
    private $release_archive;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var ScriptBuilder
     */
    private $builder;

    /**
     * @var LogFormatter
     */
    private $formatter;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $cache_key;

    /**
     * @var Server
     */
    private $server;

    public function setUp()
    {
        parent::setUp();

        $deployment_id   = 12392;
        $private_key     = '/tmp/id_rsa.key';
        $release_archive = '/tmp/release.tar.gz';

        $deployment = m::mock(Deployment::class);
        $deployment->shouldReceive('getAttribute')->with('id')->andReturn($deployment_id);

        $step = m::mock(DeployStep::class);

        $cache = m::mock(Cache::class);

        $server = m::mock(Server::class);

        $formatter = m::mock(LogFormatter::class);

        $filesystem = m::mock(Filesystem::class);

        $builder = m::mock(ScriptBuilder::class);
        $builder->shouldReceive('setup')->once()->with($deployment, $step, $release_archive, $private_key);

        $this->cache_key       = AbortDeployment::CACHE_KEY_PREFIX . $deployment_id;
        $this->deployment      = $deployment;
        $this->step            = $step;
        $this->server          = $server;
        $this->cache           = $cache;
        $this->formatter       = $formatter;
        $this->filesystem      = $filesystem;
        $this->builder         = $builder;
        $this->private_key     = $private_key;
        $this->release_archive = $release_archive;
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::run
     */
    public function testHandle()
    {
        $this->step->shouldReceive('getAttribute')->with('servers')->andReturn(new Collection());

        $job = new RunDeploymentStep($this->deployment, $this->step, $this->private_key, $this->release_archive);
        $job->handle($this->cache, $this->formatter, $this->filesystem, $this->builder);
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::run
     * @covers ::sendFilesForStep
     * @covers ::runDeploymentStepOnServer
     */
    public function testRun()
    {
        $log = $this->mockLog();

        $log->shouldReceive('setAttribute')->with('status', ServerLog::COMPLETED);

        $this->cache->shouldReceive('pull')->with($this->cache_key)->andReturnNull();

        $this->step->shouldReceive('getAttribute')->with('stage')->andReturn(Command::BEFORE_INSTALL);

        $job = new RunDeploymentStep($this->deployment, $this->step, $this->private_key, $this->release_archive);
        $job->handle($this->cache, $this->formatter, $this->filesystem, $this->builder);
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::run
     * @covers ::sendFilesForStep
     * @covers ::runDeploymentStepOnServer
     */
    public function testRunWithCacheEntryThrowsCancelledDeploymentException()
    {
        $this->expectException(CancelledDeploymentException::class);

        $log = $this->mockLog();

        $log->shouldNotReceive('setAttribute')->with('status', ServerLog::COMPLETED);
        $log->shouldReceive('setAttribute')->with('status', ServerLog::CANCELLED);

        $this->cache->shouldReceive('pull')->with($this->cache_key)->andReturn(true);

        $this->step->shouldReceive('getAttribute')->with('stage')->andReturn(Command::BEFORE_INSTALL);

        $job = new RunDeploymentStep($this->deployment, $this->step, $this->private_key, $this->release_archive);
        $job->handle($this->cache, $this->formatter, $this->filesystem, $this->builder);
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::run
     * @covers ::sendFilesForStep
     * @covers ::runDeploymentStepOnServer
     */
    public function testRunWithCacheEntryDoesNotThrowCancelledDeploymentExceptionWhenTooLate()
    {
        $log = $this->mockLog();

        $log->shouldReceive('setAttribute')->with('status', ServerLog::COMPLETED);
        $log->shouldNotReceive('setAttribute')->with('status', ServerLog::CANCELLED);

        $this->cache->shouldReceive('pull')->with($this->cache_key)->andReturn(true);

        $this->step->shouldReceive('getAttribute')->with('stage')->andReturn(Command::AFTER_ACTIVATE);

        $job = new RunDeploymentStep($this->deployment, $this->step, $this->private_key, $this->release_archive);
        $job->handle($this->cache, $this->formatter, $this->filesystem, $this->builder);
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::run
     * @covers ::sendFilesForStep
     * @covers ::runDeploymentStepOnServer
     */
    public function testRunOnCloneStepUploadsArchive()
    {
        $path       = '/var/www/deployer';
        $release_id = 20171601161556;

        $log = $this->mockLog();

        $this->expectsJobs(SendFileToServer::class);

        $this->server->shouldReceive('getAttribute')->with('clean_path')->andReturn($path);
        $this->deployment->shouldReceive('getAttribute')->with('release_id')->andReturn($release_id);

        $log->shouldReceive('setAttribute')->with('status', ServerLog::COMPLETED);

        $this->cache->shouldReceive('pull')->with($this->cache_key)->andReturnNull();

        $this->step->shouldReceive('getAttribute')->with('stage')->andReturn(Command::DO_CLONE);

        $job = new RunDeploymentStep($this->deployment, $this->step, $this->private_key, $this->release_archive);
        $job->handle($this->cache, $this->formatter, $this->filesystem, $this->builder);
    }

    private function mockLog()
    {
        $started_at  = Carbon::create(2017, 2, 1, 12, 45, 54, 'UTC');
        $finished_at = Carbon::create(2017, 2, 1, 12, 47, 12, 'UTC');

        $log = m::mock(ServerLog::class);
        $log->shouldReceive('setAttribute')->with('status', ServerLog::RUNNING);
        $log->shouldReceive('setAttribute')->with('started_at', $started_at);
        $log->shouldReceive('setAttribute')->with('finished_at', $finished_at);
        $log->shouldReceive('freshTimestamp')->once()->andReturn($started_at);
        $log->shouldReceive('freshTimestamp')->once()->andReturn($finished_at);
        $log->shouldReceive('getAttribute')->with('server')->andReturn($this->server);
        $log->shouldReceive('save')->twice();

        $this->step->shouldReceive('getAttribute')->with('servers')->andReturn(new Collection([$log]));

        $this->builder->shouldReceive('buildScript')->once()->with($this->server)->andReturnNull();

        return $log;
    }
}