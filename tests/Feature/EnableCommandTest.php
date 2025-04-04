<?php

namespace Tests\Feature;

use App\Commands\EnableCommand;
use App\Exceptions\InvalidServiceShortnameException;
use App\Services;
use App\Services\MeiliSearch;
use App\Services\PostgreSql;
use App\Shell\Docker;
use Tests\TestCase;

class EnableCommandTest extends TestCase
{
    /** @test */
    function it_can_filter_options_based_on_search_term()
    {
        $services = [
            'meilisearch' => 'App\Services\MeiliSearch',
            $postgres = 'postgresql' => $fqcn = 'App\Services\PostgreSql',
        ];

        $this->mock(Docker::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(true);
            $mock->shouldReceive('isDockerServiceRunning')->andReturn(true);
        });

        $this->mock(Services::class, function ($mock) use ($services, $fqcn) {
            $mock->shouldReceive('all')->andReturn($services);
            $mock->shouldReceive('get')->andReturn($fqcn);
        });

        $this->mock(PostgreSql::class, function ($mock) {
            $mock->shouldReceive('enable')->once();
        });

        $menuItems = [
            $postgres => 'Database: PostgreSQL',
        ];

        $this->artisan('enable')
            ->expectsSearch('Takeout containers to enable', $postgres, 'postgres', $menuItems)
            ->assertExitCode(0);
    }

    /** @test */
    function it_finds_services_by_shortname()
    {
        $service = 'meilisearch';

        $this->mock(MeiliSearch::class, function ($mock) use ($service) {
            $mock->shouldReceive('enable')->once();
            $mock->shouldReceive('shortName')->once()->andReturn($service);
        });

        $this->mock(Docker::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(true);
            $mock->shouldReceive('isDockerServiceRunning')->andReturn(true);
        });

        $this->artisan('enable ' . $service);
    }

    /** @test */
    function it_finds_multiple_services()
    {
        $meilisearch = 'meilisearch';
        $postgres = 'postgres';

        $this->mock(MeiliSearch::class, function ($mock) use ($meilisearch) {
            $mock->shouldReceive('enable')->once();
            $mock->shouldReceive('shortName')->andReturn($meilisearch);
        });

        $this->mock(PostgreSql::class, function ($mock) use ($postgres) {
            $mock->shouldReceive('enable')->once();
            $mock->shouldReceive('shortName')->andReturn($postgres);
        });

        $this->mock(Docker::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(true);
            $mock->shouldReceive('isDockerServiceRunning')->andReturn(true);
        });

        $this->artisan("enable {$meilisearch} {$postgres}");
    }

    /** @test */
    function it_displays_error_if_invalid_shortname_passed()
    {
        $this->mock(Docker::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(true);
            $mock->shouldReceive('isDockerServiceRunning')->andReturn(true);
        });

        $this->expectException(InvalidServiceShortnameException::class);
        $this->artisan('enable asdfasdfadsfasdfadsf')
            ->assertExitCode(0);
    }

    /** @test */
    function it_removes_options()
    {
        $command = new EnableCommand;

        $cli = explode(' ', "./takeout enable meilisearch postgresql mysql --default -- -e 'abc' --other-flag");
        $this->assertEquals(['meilisearch', 'postgresql', 'mysql'], $command->removeOptions($cli));

        $cli = explode(' ', "./takeout enable meilisearch -- -e MEILI_MASTER_KEY='abc'");
        $this->assertEquals(['meilisearch'], $command->removeOptions($cli));
    }

    /** @test */
    function it_extracts_passthrough_options()
    {
        $cli = explode(
            ' ',
            "./takeout enable meilisearch postgresql mysql --default -- -e 'abc' --other-flag -t \"double-quote\""
        );

        $command = new EnableCommand;

        $this->assertEquals(
            ['-e', "'abc'", '--other-flag', '-t', '"double-quote"'],
            $command->extractPassthroughOptions($cli)
        );
    }
}
