<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Tests\Unit;

/**
 * Test for lapi remediation.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

use CrowdSec\Common\Logger\FileLog;
use CrowdSec\LapiClient\Bouncer;
use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use CrowdSec\RemediationEngine\CacheStorage\Memcached;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CacheStorage\Redis;
use CrowdSec\RemediationEngine\Constants;
use CrowdSec\RemediationEngine\Constants as RemConstants;
use CrowdSec\RemediationEngine\LapiRemediation;
use CrowdSec\RemediationEngine\Tests\Constants as TestConstants;
use CrowdSec\RemediationEngine\Tests\MockedData;
use CrowdSec\RemediationEngine\Tests\PHPUnitUtil;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::__construct
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::cleanCachedValues
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getAdapter
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getMaxExpiration
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::__construct
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::clear
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::commit
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::configure
 * @uses \CrowdSec\RemediationEngine\CacheStorage\PhpFiles::__construct
 * @uses \CrowdSec\RemediationEngine\CacheStorage\PhpFiles::configure
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Redis::__construct
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Redis::configure
 * @uses \CrowdSec\RemediationEngine\Configuration\AbstractRemediation::addCommonNodes
 * @uses \CrowdSec\RemediationEngine\Configuration\Cache\Memcached::getConfigTreeBuilder
 * @uses \CrowdSec\RemediationEngine\Configuration\Cache\PhpFiles::getConfigTreeBuilder
 * @uses \CrowdSec\RemediationEngine\Configuration\Cache\Redis::getConfigTreeBuilder
 * @uses \CrowdSec\RemediationEngine\Configuration\AbstractRemediation::validateCommon
 * @uses \CrowdSec\RemediationEngine\Decision::getOrigin
 * @uses \CrowdSec\RemediationEngine\Decision::toArray
 * @uses \CrowdSec\RemediationEngine\Configuration\Lapi::getConfigTreeBuilder
 * @uses \CrowdSec\RemediationEngine\Configuration\AbstractRemediation::addGeolocationNodes
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getIpCachedVariables
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getIpVariables
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::saveItemWithDuration()
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::setIpVariables
 * @uses \CrowdSec\RemediationEngine\Geolocation::__construct
 * @uses \CrowdSec\RemediationEngine\Geolocation::getMaxMindCountryResult
 * @uses \CrowdSec\RemediationEngine\Geolocation::handleCountryResultForIp
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::getItem
 * @uses \CrowdSec\RemediationEngine\Configuration\AbstractCache::addCommonNodes
 *
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::getOriginsCount
 *
 * @uses \CrowdSec\RemediationEngine\AbstractRemediation::sortDecisionsByPriority
 *
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::capRemediationLevel
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::getOriginsCountItem
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::getFirstCall
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::storeFirstCall
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::handleDecisionOrigin
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::updateMetricsOriginsCount
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::getCacheStorage
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::handleIpV6RangeDecisions
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::getIpType
 * @covers \CrowdSec\RemediationEngine\Decision::setScope
 * @covers \CrowdSec\RemediationEngine\Decision::setValue
 * @covers \CrowdSec\RemediationEngine\Decision::getExpiresAt
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::__construct
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::normalize
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::handleDecisionIdentifier
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::parseDurationToSeconds
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::handleDecisionExpiresAt
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::__construct
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::configure
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::getConfig
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::getIpRemediation
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::storeDecisions
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::refreshDecisions
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::getStreamDecisions
 * @covers \CrowdSec\RemediationEngine\Configuration\Capi::getConfigTreeBuilder
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::removeDecisions
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::clear
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::commit
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::format
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getCacheKey
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getCachedIndex
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getRangeIntForIp
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::handleRangeScoped
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::remove
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::removeDecision
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::store
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::storeDecision
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::updateDecisionItem
 * @covers \CrowdSec\RemediationEngine\Decision::__construct
 * @covers \CrowdSec\RemediationEngine\Decision::getIdentifier
 * @covers \CrowdSec\RemediationEngine\Decision::getScope
 * @covers \CrowdSec\RemediationEngine\Decision::getType
 * @covers \CrowdSec\RemediationEngine\Decision::getValue
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::comparePriorities
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::manageRange
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::saveDeferred
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getTags
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getItem
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::retrieveDecisionsForIp
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::convertRawDecision
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::convertRawDecisionsToDecisions
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::validateRawDecision
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::clearCache
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::pruneCache
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::prune
 * @covers \CrowdSec\RemediationEngine\Configuration\AbstractRemediation::getDefaultOrderedRemediations
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::getAllCachedDecisions
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::getCountryForIp
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::upsertItem
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::getScopes
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::isWarm
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::warmUp
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::getClient
 * @covers \CrowdSec\RemediationEngine\Configuration\Lapi::addAppSecNodes
 * @covers \CrowdSec\RemediationEngine\Configuration\Lapi::validateAppSec
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::processCachedDecisions
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::retrieveRemediationFromCachedDecisions
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::buildMetricsItems
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::pushUsageMetrics
 * @covers \CrowdSec\RemediationEngine\LapiRemediation::storeMetricsLastSent
 */
final class LapiRemediationTest extends AbstractRemediation
{
    /**
     * @var AbstractCache
     */
    private $cacheStorage;
    /**
     * @var string
     */
    private $debugFile;
    /**
     * @var FileLog
     */
    private $logger;
    /**
     * @var Memcached
     */
    private $memcachedStorage;
    /**
     * @var PhpFiles
     */
    private $phpFileStorage;
    /**
     * @var PhpFiles
     */
    private $phpFileStorageWithTags;
    /**
     * @var string
     */
    private $prodFile;
    /**
     * @var Redis
     */
    private $redisStorage;
    /**
     * @var Redis
     */
    private $redisStorageWithTags;
    /**
     * @var vfsStreamDirectory
     */
    private $root;
    /**
     * @var Bouncer
     */
    private $bouncer;

    public function cacheTypeProvider(): array
    {
        return [
            'PhpFilesAdapter' => ['PhpFilesAdapter'],
            'RedisAdapter' => ['RedisAdapter'],
            'MemcachedAdapter' => ['MemcachedAdapter'],
            'PhpFilesAdapterWithTags' => ['PhpFilesAdapterWithTags'],
            'RedisAdapterWithTags' => ['RedisAdapterWithTags'],
        ];
    }

    /**
     * set up test environment.
     */
    public function setUp(): void
    {
        $this->root = vfsStream::setup(TestConstants::TMP_DIR);
        $currentDate = date('Y-m-d');
        $this->debugFile = 'debug-' . $currentDate . '.log';
        $this->prodFile = 'prod-' . $currentDate . '.log';
        $this->logger = new FileLog(['log_directory_path' => $this->root->url(), 'debug_mode' => true, 'log_rotator' => true]);
        $this->bouncer = $this->getBouncerMock();

        $cachePhpfilesConfigs = ['fs_cache_path' => $this->root->url()];
        $mockedMethods = ['retrieveDecisionsForIp', 'retrieveDecisionsForCountry'];
        $this->phpFileStorage =
            $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs, $this->logger, $mockedMethods);
        $this->phpFileStorageWithTags =
            $this->getCacheMock('PhpFilesAdapter', array_merge($cachePhpfilesConfigs, ['use_cache_tags' => true]),
                $this->logger, $mockedMethods);
        $cacheMemcachedConfigs = [
            'memcached_dsn' => getenv('memcached_dsn') ?: 'memcached://memcached:11211',
        ];
        $this->memcachedStorage =
            $this->getCacheMock('MemcachedAdapter', $cacheMemcachedConfigs, $this->logger, $mockedMethods);
        $cacheRedisConfigs = [
            'redis_dsn' => getenv('redis_dsn') ?: 'redis://redis:6379',
        ];
        $this->redisStorage = $this->getCacheMock('RedisAdapter', $cacheRedisConfigs, $this->logger, $mockedMethods);
        $this->redisStorageWithTags = $this->getCacheMock('RedisAdapter', array_merge($cacheRedisConfigs,
            ['use_cache_tags' => true]),
            $this->logger,
            $mockedMethods);
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testCacheActions($cacheType)
    {
        $this->setCache($cacheType);
        $remediationConfigs = [];
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, null);
        $result = $remediation->clearCache();
        $this->assertEquals(
            true,
            $result,
            'Should clear cache'
        );

        if ('PhpFilesAdapter' === $cacheType) {
            $result = $remediation->pruneCache();
            $this->assertEquals(
                true,
                $result,
                'Should prune cache'
            );
        }
    }

    public function testFailedDeferred()
    {
        // Test failed deferred
        $this->bouncer->method('getStreamDecisions')->will(
            $this->onConsecutiveCalls(
                MockedData::DECISIONS['new_ip_v4_double'], // Test 1 : new IP decision (ban) (save ok)
                MockedData::DECISIONS['new_ip_v4_other'],  // Test 2 : new IP decision (ban) (failed deferred)
                MockedData::DECISIONS['deleted_ip_v4']     // Test 3 : deleted IP decision (failed deferred)
            )
        );
        $cachePhpfilesConfigs = ['fs_cache_path' => $this->root->url()];
        $mockedMethods = [];
        $this->cacheStorage =
            $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs, $this->logger, $mockedMethods);
        $remediationConfigs = [];
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, $this->logger);

        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 2, 'deleted' => 0],
            $result,
            'Refresh count should be correct for 2 news'
        );

        // Test 2
        $mockedMethods = ['saveDeferred'];
        $this->cacheStorage =
            $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs, $this->logger, $mockedMethods);
        $remediationConfigs = [];
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, $this->logger);

        $this->cacheStorage->method('saveDeferred')->will(
            $this->onConsecutiveCalls(
                false
            )
        );
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct for failed deferred store'
        );
        // Test 3
        $mockedMethods = ['saveDeferred'];
        $this->cacheStorage =
            $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs, $this->logger, $mockedMethods);
        $remediationConfigs = [];
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, $this->logger);
        $this->cacheStorage->method('saveDeferred')->will(
            $this->onConsecutiveCalls(
                false
            )
        );
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct for failed deferred remove'
        );
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testGetIpRemediationInStreamMode($cacheType)
    {
        $this->setCache($cacheType);

        $remediationConfigs = ['stream_mode' => true];

        // Test with null logger
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, null);
        // Test stream mode value
        $this->assertEquals(
            true,
            $remediation->getConfig('stream_mode'),
            'Stream mode should be true'
        );
        // Test default configs
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $remediation->getConfig('fallback_remediation'),
            'Default fallback should be bypass'
        );
        $this->assertEquals(
            [Constants::REMEDIATION_BAN, Constants::REMEDIATION_CAPTCHA, Constants::REMEDIATION_BYPASS],
            $remediation->getConfig('ordered_remediations'),
            'Default ordered remediation should be as expected'
        );
        // Prepare next tests
        $this->cacheStorage->method('retrieveDecisionsForIp')->will(
            $this->onConsecutiveCalls(
                [AbstractCache::STORED => []],  // Test 1 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 1 : retrieve empty range decisions
                [AbstractCache::STORED => [[
                    'bypass',
                    999999999999,
                    'clean-bypass-ip-1.2.3.4',
                    'clean',
                ]]],                            // Test 2 : retrieve cached bypass
                [AbstractCache::STORED => []],  // Test 2 : retrieve empty range
                [AbstractCache::STORED => [[
                    'bypass',
                    999999999999,
                    'clean-bypass-ip-1.2.3.4',
                    'clean',
                ]]],                            // Test 3 : retrieve bypass for ip
                [AbstractCache::STORED => [[
                    'ban',
                    999999999999,
                    'capi-ban-ip-1.2.3.4',
                    'CAPI',
                ]]],                            // Test 3 : retrieve ban for range
                [AbstractCache::STORED => [[
                    'ban',
                    311738199, //  Sunday 18 November 1979
                    'capi-ban-ip-1.2.3.4',
                    'CAPI',
                ]]],                            // Test 4 : retrieve expired ban ip
                [AbstractCache::STORED => []]   // Test 4 : retrieve empty range
            )
        );
        // Test 1
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $result['remediation'],
            'Uncached (clean) IP should return a bypass remediation'
        );

        $adapter = $this->cacheStorage->getAdapter();
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_CACHE_KEY));
        $this->assertEquals(
            false,
            $item->isHit(),
            'Remediation should not have been cached'
        );

        // Test 2
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $result['remediation'],
            'Cached clean IP should return a bypass remediation'
        );
        // Test 3
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);
        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $result['remediation'],
            'Remediations should be ordered by priority'
        );
        // Test 4
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $result['remediation'],
            'Expired cached remediations should have been cleaned'
        );
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testGetIpRemediationInLiveMode($cacheType)
    {
        $this->setCache($cacheType);

        $remediationConfigs = ['stream_mode' => false];
        // Prepare next tests
        $currentTime = time();
        $expectedCleanTime = $currentTime + Constants::CACHE_EXPIRATION_FOR_CLEAN_IP;
        $this->cacheStorage->method('retrieveDecisionsForIp')->will(
            $this->onConsecutiveCalls(
                [AbstractCache::STORED => []],  // Test 1 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 1 : retrieve empty range decisions
                [AbstractCache::STORED => [[
                    'bypass',
                    $expectedCleanTime,
                    'clean-bypass-ip-1.2.3.4',
                    'clean',
                ]]],                            // Test 2 : retrieve cached bypass
                [AbstractCache::STORED => []],  // Test 2 : retrieve empty range
                [AbstractCache::STORED => []],  // Test 3 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 3 : retrieve empty range decisions
                [AbstractCache::STORED => []],  // Test 4 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 4 : retrieve empty range decisions
                [AbstractCache::STORED => [[
                    'bypass',
                    $expectedCleanTime,
                    'clean-bypass-ip-1.2.3.4',
                    'clean',
                ]]],                            // Test 5 : retrieve cached bypass
                [AbstractCache::STORED => []],  // Test 5 : retrieve empty range
                [AbstractCache::STORED => [[
                    'bypass',
                    $expectedCleanTime,
                    'clean-bypass-ip-1.2.3.4',
                    'clean',
                ]]],                            // Test 5 bis : retrieve cached bypass
                [AbstractCache::STORED => []],  // Test 5 bis : retrieve empty range
                [AbstractCache::STORED => []],  // Test 6 : retrieve empty IP decisions
                [AbstractCache::STORED => []]  // Test 6 : retrieve empty range decisions
            )
        );
        $this->bouncer->method('getFilteredDecisions')->will(
            $this->onConsecutiveCalls(
                [],  // Test 1 : retrieve empty IP decisions
                [
                    [
                        'scope' => 'ip',
                        'value' => TestConstants::IP_V4,
                        'type' => 'captcha',
                        'origin' => 'lapi',
                        'duration' => '1h',
                    ],
                    [
                        'scope' => 'ip',
                        'value' => TestConstants::IP_V4,
                        'type' => 'ban',
                        'origin' => 'lapi',
                        'duration' => '1h',
                    ],
                ],    // Test 3
                [
                    [
                        'scope' => 'range',
                        'value' => TestConstants::IP_V6 . '/24',
                        'type' => 'ban',
                        'origin' => 'lapi',
                        'duration' => '1h',
                    ],
                ],   // Test 4 : IPv6 range scoped
                [
                    [
                        'scope' => 'ip',
                        'value' => TestConstants::IP_V4_4,
                        'type' => 'ban',
                        'origin' => 'lists',
                        'scenario' => 'crowdsec_proxy',
                        'duration' => '1h',
                    ],
                ] // Test 6 : origin lists
            )
        );

        // Test with null logger
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, null);
        // Test stream mode value
        $this->assertEquals(
            false,
            $remediation->getConfig('stream_mode'),
            'Stream mode should be false'
        );
        // Test default configs
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $remediation->getConfig('fallback_remediation'),
            'Default fallback should be bypass'
        );
        $this->assertEquals(
            [Constants::REMEDIATION_BAN, Constants::REMEDIATION_CAPTCHA, Constants::REMEDIATION_BYPASS],
            $remediation->getConfig('ordered_remediations'),
            'Default ordered remediation should be as expected'
        );

        // Direct LAPI call will be done only if there is no cached decisions (Test1, Test 3, Test 6)
        $this->bouncer->expects($this->exactly(4))->method('getFilteredDecisions');

        // Test 1 (No cached items and no active decision)

        $result = $remediation->getIpRemediation(TestConstants::IP_V4);

        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $result['remediation'],
            'Uncached (clean) and with no active decision should return a bypass remediation'
        );

        $adapter = $this->cacheStorage->getAdapter();
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_CACHE_KEY));
        $this->assertEquals(
            true,
            $item->isHit(),
            'Remediation should have been cached'
        );
        $cachedItem = $item->get();
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $cachedItem[0][AbstractCache::INDEX_MAIN],
            'Bypass should have been cached'
        );
        $this->assertTrue(
            $expectedCleanTime <= $cachedItem[0][AbstractCache::INDEX_EXP]
            && $cachedItem[0][AbstractCache::INDEX_EXP] <= $expectedCleanTime + 1,
            'Should return current time + clean ip duration config'
        );
        $this->assertEquals(
            'clean-bypass-ip-1.2.3.4',
            $cachedItem[0][AbstractCache::INDEX_ID],
            'Should return correct identifier'
        );
        $this->assertEquals(
            'clean',
            $cachedItem[0][AbstractCache::INDEX_ORIGIN],
            'Should return correct origin'
        );

        $item = $this->cacheStorage->getItem(AbstractCache::CONFIG);
        $this->assertEquals(
            true,
            $item->isHit(),
            'Config item should be cached'
        );
        $configItem = $item->get();
        $this->assertEqualsWithDelta(
            [
                AbstractCache::FIRST_LAPI_CALL => time(),
            ],
            $configItem,
            1000, // 1 second delta to avoid false negative
            'Config cache item should be as expected'
        );
        $originalFirstCall = $configItem[AbstractCache::FIRST_LAPI_CALL];
        sleep(1); // To test that first LAPI call is cached and do not change
        // Test 2 (cached decisions)
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $result['remediation'],
            'Cached (clean) should return a bypass remediation'
        );

        // Additional tests
        $item = $adapter->getItem(base64_encode(AbstractCache::CONFIG));
        $this->assertEquals(
            true,
            $item->isHit(),
            'First LAPI call should be cached'
        );
        $finalConfigItem = $item->get();
        $finalFirstCall = $finalConfigItem[AbstractCache::FIRST_LAPI_CALL];
        $this->assertEquals(
            $originalFirstCall,
            $finalFirstCall,
            'First LAPI call should be the same as at beginning'
        );
        // Test 3 (no cached decision and 2 actives IP decisions)
        $this->cacheStorage->clear();

        $result = $remediation->getIpRemediation(TestConstants::IP_V4);

        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $result['remediation'],
            'Should return a ban remediation'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_CACHE_KEY));
        $cachedItem = $item->get();
        $this->assertCount(2, $cachedItem, 'Should have cache 2 decisions for IP');

        // Test 4 (no cached decision and 1 active IPv6 range decision)
        $this->cacheStorage->clear();
        $result = $remediation->getIpRemediation(TestConstants::IP_V6);
        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $result['remediation'],
            'Should return a ban remediation'
        );
        $item = $adapter->getItem(base64_encode(RemConstants::SCOPE_IP . AbstractCache::SEP . TestConstants::IP_V6_CACHE_KEY));
        $cachedItem = $item->get();
        $this->assertCount(1, $cachedItem, 'Should have cache 1 decisions for IP');
        $this->assertEquals($cachedItem[0][0], 'ban', 'Should be a ban');

        // Test 5 : merge origins count
        $remediation->getIpRemediation(TestConstants::IP_V4);

        // Test 5 bis : merge origins count
        $remediation->getIpRemediation(TestConstants::IP_V4);

        // Test 6 : origin lists
        $result = $remediation->getIpRemediation(TestConstants::IP_V4_4);
        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $result['remediation'],
            'Should return a ban remediation'
        );
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testPushUsageMetricsInLiveMode($cacheType)
    {
        $this->setCache($cacheType);
        $remediationConfigs = ['stream_mode' => false];
        // Prepare next tests
        $currentTime = time();
        $this->cacheStorage->method('retrieveDecisionsForIp')->will(
            // We simulate that cache never contains any decision
            $this->onConsecutiveCalls(
                [AbstractCache::STORED => []],  // Test 1 / Call1 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 1 / Call1 : retrieve empty range decisions
                [AbstractCache::STORED => []],  // Test 1 / Call2 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 1 / Call2 : retrieve empty range decisions
                [AbstractCache::STORED => []],  // Test 1 / Call3 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 1 / Call3 : retrieve empty range decisions
                [AbstractCache::STORED => []],  // Test 2 / Call1 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 2 / Call1 : retrieve empty range decisions
                [AbstractCache::STORED => []],  // Test 3 / Call1 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 3 / Call1 : retrieve empty range decisions
                [AbstractCache::STORED => []],  // Test 3 / Call2 : retrieve empty IP decisions
                [AbstractCache::STORED => []]  // Test 3 / Call2 : retrieve empty range decisions
            )
        );
        $this->bouncer->method('getFilteredDecisions')->will(
            $this->onConsecutiveCalls(
                [],  // Test 1 / Call1 : retrieve empty IP decisions (final metrics count will be a bypass)
                [
                    [
                        'scope' => 'ip',
                        'value' => TestConstants::IP_V4,
                        'type' => 'captcha',
                        'origin' => 'cscli',
                        'duration' => '1h',
                    ],
                    [
                        'scope' => 'ip',
                        'value' => TestConstants::IP_V4,
                        'type' => 'ban',
                        'origin' => 'CAPI',
                        'duration' => '1h',
                    ], // Test 1 / Call2 : retrieve ban and captcha (final metrics count will be a ban from CAPI)
                ],
                [
                    [
                        'scope' => 'ip',
                        'value' => TestConstants::IP_V4_2,
                        'type' => 'captcha',
                        'origin' => 'lists:tor',
                        'duration' => '1h',
                    ], // Test 1 / Call3 : retrieve captcha (final metrics count will be a captcha from lists-tor)
                ],
                [
                    [
                        'scope' => 'ip',
                        'value' => TestConstants::IP_V4_3,
                        'type' => 'captcha',
                        'origin' => 'lists:tor',
                        'duration' => '1h',
                    ], // Test 2 / Call1 : retrieve captcha (final metrics count will be a captcha from lists-tor)
                ],
                [
                    [
                        'scope' => 'ip',
                        'value' => TestConstants::IP_V4_4,
                        'type' => 'captcha',
                        'origin' => 'lists:tor',
                        'duration' => '1h',
                    ], // Test 3 / Call1 : retrieve captcha (final metrics count will be a captcha from lists-tor)
                ],
                [
                    [
                        'scope' => 'ip',
                        'value' => TestConstants::IP_V4_5,
                        'type' => 'captcha',
                        'origin' => 'lists:tor',
                        'duration' => '1h',
                    ], // Test 3 / Call2 : retrieve captcha (final metrics count will be a captcha from lists-tor)
                ]
            )
        );
        // Test 1 : push metrics
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, null);
        // Call 1
        $remediation->getIpRemediation(TestConstants::IP_V4);
        $item = $this->cacheStorage->getItem(AbstractCache::CONFIG);
        $configItem = $item->get();
        $this->assertEqualsWithDelta(
            [
                AbstractCache::FIRST_LAPI_CALL => $currentTime,
            ],
            $configItem,
            1, // 1 second delta to avoid false negative
            'First call should have been cached'
        );
        $originalFirstCall = $configItem[AbstractCache::FIRST_LAPI_CALL];
        $this->assertArrayNotHasKey(
            AbstractCache::LAST_METRICS_SENT,
            $configItem,
            'Last sent Usage metrics should not be cached');
        // We simulate what a bouncer should do: update clean/bypass count
        $remediation->updateMetricsOriginsCount('clean', 'bypass');
        // Call 2
        $remediation->getIpRemediation(TestConstants::IP_V4);
        // We simulate what a bouncer should do: update CAPI/ban count
        $remediation->updateMetricsOriginsCount('CAPI', 'ban');
        $originsCount = $remediation->getOriginsCount();
        $this->assertEquals(
            ['clean' => ['bypass' => 1], 'CAPI' => ['ban' => 1]],
            $originsCount,
            'Origin count should be cached'
        );
        // Call 3
        $remediation->getIpRemediation(TestConstants::IP_V4_2);
        // We simulate what a bouncer should do: update lists:tor/captcha count
        $remediation->updateMetricsOriginsCount('lists:tor', 'captcha');
        $originsCount = $remediation->getOriginsCount();
        $this->assertEquals(
            [
                'clean' => ['bypass' => 1],
                'CAPI' => ['ban' => 1],
                'lists:tor' => ['captcha' => 1],
            ],
            $originsCount,
            'Origin count should be cached'
        );

        $result = $remediation->pushUsageMetrics('test-remediation-php-unit', 'v0.0.0', 'crowdsec-php-bouncer-unit-test');
        $this->assertArrayHasKey('remediation_components', $result, 'Should return a remediation_components key');
        $items = $result['remediation_components'][0]['metrics'][0]['items'];

        $this->assertEquals(
            $items[0],
            [
                'name' => 'dropped',
                'value' => 1,
                'unit' => 'request',
                'labels' => [
                    'origin' => 'CAPI',
                    'remediation' => 'ban',
                ],
            ],
            'Should have CAPI/ban metrics' . json_encode($items[0])
        );
        $this->assertEquals(
            $items[1],
            [
                'name' => 'dropped',
                'value' => 1,
                'unit' => 'request',
                'labels' => [
                    'origin' => 'lists:tor',
                    'remediation' => 'captcha',
                ],
            ],
            'Should have lists:tor/captcha metrics' . json_encode($items[1])
        );
        $this->assertEquals(
            $items[2],
            [
                'name' => 'processed',
                'value' => 3,
                'unit' => 'request',
            ],
            'Should have processed metrics' . json_encode($items[2])
        );
        $firstPushTime = time();
        $item = $this->cacheStorage->getItem(AbstractCache::CONFIG);
        $configItem = $item->get();
        $this->assertEqualsWithDelta(
            [
                AbstractCache::LAST_METRICS_SENT => $firstPushTime,
                AbstractCache::FIRST_LAPI_CALL => $originalFirstCall,
            ],
            $configItem,
            1, // 1 second delta to avoid false negative
            'Last sent should have been cached'
        );
        $originsCount = $remediation->getOriginsCount();
        $this->assertEquals(
            [
                'clean' => ['bypass' => 0],
                'CAPI' => ['ban' => 0],
                'lists:tor' => ['captcha' => 0],
            ],
            $originsCount,
            'Origin count should be reset'
        );

        // Test 2 : push metrics again after some delay
        // Call 1
        sleep(1);
        $remediation->getIpRemediation(TestConstants::IP_V4_3);
        // We simulate what a bouncer should do: update lists:tor/captcha count
        $remediation->updateMetricsOriginsCount('lists:tor', 'captcha');
        $originsCount = $remediation->getOriginsCount();
        $this->assertEquals(
            [
                'clean' => ['bypass' => 0],
                'CAPI' => ['ban' => 0],
                'lists:tor' => ['captcha' => 1],
            ],
            $originsCount,
            'Origin count should be updated'
        );
        $secondPushTime = time();
        $result = $remediation->pushUsageMetrics('test-remediation-php-unit', 'v0.0.0', 'crowdsec-php-bouncer-unit-test');
        $item = $this->cacheStorage->getItem(AbstractCache::CONFIG);
        $configItem = $item->get();
        $this->assertEqualsWithDelta(
            [
                AbstractCache::LAST_METRICS_SENT => $secondPushTime,
                AbstractCache::FIRST_LAPI_CALL => $originalFirstCall,
            ],
            $configItem,
            1, // 1 second delta to avoid false negative
            'Last sent should have been cached'
        );
        $this->assertEqualsWithDelta(
            1,
            $result['remediation_components'][0]['metrics'][0]['meta']['window_size_seconds'],
            1, // 1s to avoid false negative
            'window_size_seconds should be 1 seconds'
        );
        $originsCount = $remediation->getOriginsCount();
        $this->assertEquals(
            [
                'clean' => ['bypass' => 0],
                'CAPI' => ['ban' => 0],
                'lists:tor' => ['captcha' => 0],
            ],
            $originsCount,
            'Origin count should be reset'
        );
        // Test 3 : push metrics and concurrent getRemediationIp call
        $remediation->getIpRemediation(TestConstants::IP_V4_4);
        // We simulate what a bouncer should do: update lists:tor/captcha count
        $remediation->updateMetricsOriginsCount('lists:tor', 'captcha');
        $originsCount = $remediation->getOriginsCount();
        $this->assertEquals(
            [
                'clean' => ['bypass' => 0],
                'CAPI' => ['ban' => 0],
                'lists:tor' => ['captcha' => 1],
            ],
            $originsCount,
            'Origin count should be updated'
        );
        $thirdPushTime = time();
        // Trying to test simultaneous call
        $result = $remediation->pushUsageMetrics('test-remediation-php-unit', 'v0.0.0', 'crowdsec-php-bouncer-unit-test');
        $remediation->getIpRemediation(TestConstants::IP_V4_5);
        // We simulate what a bouncer should do: update lists:tor/captcha count
        $remediation->updateMetricsOriginsCount('lists:tor', 'captcha');
        $originsCount = $remediation->getOriginsCount();
        $this->assertEquals(
            [
                'clean' => ['bypass' => 0],
                'CAPI' => ['ban' => 0],
                'lists:tor' => ['captcha' => 1],
            ],
            $originsCount,
            'Origin count should be updated with -1 + 1 (i.e same as before)'
        );
        $item = $this->cacheStorage->getItem(AbstractCache::CONFIG);
        $configItem = $item->get();
        $this->assertEqualsWithDelta(
            [
                AbstractCache::LAST_METRICS_SENT => $thirdPushTime,
                AbstractCache::FIRST_LAPI_CALL => $originalFirstCall,
            ],
            $configItem,
            1, // 1 second delta to avoid false negative
            'Last sent should have been cached'
        );
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testPushUsageMetricsInStreamMode($cacheType)
    {
        $this->setCache($cacheType);
        $remediationConfigs = ['stream_mode' => true];
        // Prepare next tests
        $currentTime = time();
        $this->cacheStorage->method('retrieveDecisionsForIp')->will(
            $this->onConsecutiveCalls(
                [AbstractCache::STORED => [[
                    'bypass',
                    999999999999,
                    'clean-bypass-ip-' . TestConstants::IP_V4,
                    'clean',
                ]]],                            // Test 1/Call 1 : retrieve cached bypass
                [AbstractCache::STORED => []],  // Test 1/Call 1 : retrieve empty range
                [AbstractCache::STORED => [[
                    'bypass',
                    999999999999,
                    'clean-bypass-ip-' . TestConstants::IP_V4,
                    'clean',
                ]]],                            // Test 1/Call 2 : retrieve cached bypass
                [AbstractCache::STORED => []]  // Test 1/Call 2 : retrieve empty range
            )
        );
        $this->bouncer->method('getStreamDecisions')->will(
            $this->onConsecutiveCalls(
                MockedData::DECISIONS['new_ip_v4']           // Test 1 : new IP decision (ban)
            )
        );

        // Test 1 : push metrics
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, null);
        // Call 1
        $remediation->refreshDecisions();
        $remediation->getIpRemediation(TestConstants::IP_V4);
        // We simulate what a bouncer should do: update clean/bypass count
        $remediation->updateMetricsOriginsCount('clean', 'bypass');
        $item = $this->cacheStorage->getItem(AbstractCache::CONFIG);
        $configItem = $item->get();
        $this->assertEqualsWithDelta(
            [
                AbstractCache::FIRST_LAPI_CALL => $currentTime,
                AbstractCache::WARMUP => true,
            ],
            $configItem,
            1, // 1 second delta to avoid false negative
            'First call should have been cached'
        );
        $originalFirstCall = $configItem[AbstractCache::FIRST_LAPI_CALL];
        $this->assertArrayNotHasKey(
            AbstractCache::LAST_METRICS_SENT,
            $configItem,
            'Last sent Usage metrics should not be cached');
        // Call 2
        $remediation->getIpRemediation(TestConstants::IP_V4);
        // We simulate what a bouncer should do: update clean/bypass count
        $remediation->updateMetricsOriginsCount('clean', 'bypass');
        $originsCount = $remediation->getOriginsCount();
        $this->assertEquals(
            ['clean' => ['bypass' => 2]],
            $originsCount,
            'Origin count should be cached'
        );
        $result = $remediation->pushUsageMetrics('test-remediation-php-unit', 'v0.0.0', 'crowdsec-php-bouncer-unit-test');
        $this->assertArrayHasKey('remediation_components', $result, 'Should return a remediation_components key');

        $firstPushTime = time();
        $item = $this->cacheStorage->getItem(AbstractCache::CONFIG);
        $configItem = $item->get();
        $this->assertEqualsWithDelta(
            [
                AbstractCache::LAST_METRICS_SENT => $firstPushTime,
                AbstractCache::FIRST_LAPI_CALL => $originalFirstCall,
                AbstractCache::WARMUP => true,
            ],
            $configItem,
            1, // 1 second delta to avoid false negative
            'Last sent should have been cached'
        );
        $originsCount = $remediation->getOriginsCount();
        $this->assertEquals(
            [
                'clean' => ['bypass' => 0],
            ],
            $originsCount,
            'Origin count should be reset'
        );
        // Test 2: nothing to send
        $result = $remediation->pushUsageMetrics('test-remediation-php-unit', 'v0.0.0', 'crowdsec-php-bouncer-unit-test');
        $this->assertEquals(
            [],
            $result,
            'Should return an empty array'
        );
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testGetIpRemediationInLiveModeWithGeolocation($cacheType)
    {
        $databasePath = __DIR__ . '/../geolocation/GeoLite2-Country.mmdb';
        if (!file_exists($databasePath)) {
            $this->fail('For this test, there must be a MaxMind Database here: ' . $databasePath);
        }
        $this->setCache($cacheType);

        $remediationConfigs = [
            'stream_mode' => false,
            'geolocation' => [
                'cache_duration' => Constants::CACHE_EXPIRATION_FOR_GEO,
                'enabled' => true,
                'type' => 'maxmind',
                'maxmind' => [
                    'database_type' => Constants::MAXMIND_COUNTRY,
                    'database_path' => $databasePath,
                ],
            ],
        ];
        // Prepare next tests
        $currentTime = time();
        $expectedBadTime = $currentTime + Constants::CACHE_EXPIRATION_FOR_BAD_IP;
        $this->cacheStorage->method('retrieveDecisionsForIp')->will(
            $this->onConsecutiveCalls(
                [AbstractCache::STORED => []],  // Test 1 : retrieve empty IP decisions
                [AbstractCache::STORED => []]  // Test 1 : retrieve empty range decisions
            )
        );
        $this->cacheStorage->method('retrieveDecisionsForCountry')->will(
            $this->onConsecutiveCalls(
                [AbstractCache::STORED => []]  // Test 1 : retrieve empty country decisions
            )
        );
        $this->bouncer->method('getFilteredDecisions')->will(
            $this->onConsecutiveCalls(
                [],  // Test 1 : retrieve empty IP decisions
                [
                    [
                        'scope' => 'country',
                        'value' => 'AU', // 1.2.3.4 is localized in AU
                        'type' => 'captcha',
                        'origin' => 'lapi',
                        'duration' => '1h',
                    ],
                ]    // Test 1 : retrieve captcha decision for country
            )
        );

        // Test with null logger
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, null);
        // Test stream mode value
        $this->assertEquals(
            false,
            $remediation->getConfig('stream_mode'),
            'Stream mode should be false'
        );

        // Direct LAPI call will be done only if there is no cached decisions (Test1 for ip, Test 1 for country)
        $this->bouncer->expects($this->exactly(2))->method('getFilteredDecisions');

        // Test 1 (No cached items and 1 active country decision)
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);

        $this->assertEquals(
            Constants::REMEDIATION_CAPTCHA,
            $result['remediation'],
            'Should return a captcha'
        );

        $adapter = $this->cacheStorage->getAdapter();
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_CACHE_KEY));
        $this->assertEquals(
            false,
            $item->isHit(),
            'Remediation for IP should have not been cached'
        );
        $item = $adapter->getItem(base64_encode(Constants::SCOPE_COUNTRY . AbstractCache::SEP . 'AU'));
        $this->assertEquals(
            true,
            $item->isHit(),
            'Remediation for country should have been cached'
        );

        $cachedItem = $item->get();
        $this->assertEquals(
            Constants::REMEDIATION_CAPTCHA,
            $cachedItem[0][AbstractCache::INDEX_MAIN],
            'captcha should have been cached'
        );
        $this->assertTrue(
            $expectedBadTime <= $cachedItem[0][AbstractCache::INDEX_EXP]
            && $cachedItem[0][AbstractCache::INDEX_EXP] <= $expectedBadTime + 1,
            'Should return current time + bad ip duration config'
        );
        $this->assertEquals(
            'lapi-captcha-country-AU',
            $cachedItem[0][AbstractCache::INDEX_ID],
            'Should return correct indentifier'
        );
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testGetIpRemediationInStreamModeWithGeolocation($cacheType)
    {
        $databasePath = __DIR__ . '/../geolocation/GeoLite2-Country.mmdb';
        if (!file_exists($databasePath)) {
            $this->fail('For this test, there must be a MaxMind Database here: ' . $databasePath);
        }
        $this->setCache($cacheType);

        $remediationConfigs = [
            'stream_mode' => true,
            'geolocation' => [
                'cache_duration' => Constants::CACHE_EXPIRATION_FOR_GEO,
                'enabled' => true,
                'type' => 'maxmind',
                'maxmind' => [
                    'database_type' => Constants::MAXMIND_COUNTRY,
                    'database_path' => $databasePath,
                ],
            ],
        ];
        // Prepare next tests
        $currentTime = time();
        $expectedCleanTime = $currentTime + Constants::CACHE_EXPIRATION_FOR_CLEAN_IP;
        $this->cacheStorage->method('retrieveDecisionsForIp')->will(
            $this->onConsecutiveCalls(
                [AbstractCache::STORED => []],  // Test 1 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 1 : retrieve empty range decisions
                [AbstractCache::STORED => []],  // Test 2 : retrieve empty IP decisions
                [AbstractCache::STORED => []],  // Test 2 : retrieve empty range decisions
                [AbstractCache::STORED => [[
                    'ban',
                    999999999999,
                    'lapi-ban-ip-1.2.3.4',
                ]]],                            // Test 3 retrieve IP ban
                [AbstractCache::STORED => []]   // Test 3 : retrieve empty range decisions
            )
        );
        $this->cacheStorage->method('retrieveDecisionsForCountry')->will(
            $this->onConsecutiveCalls(
                [AbstractCache::STORED => []],  // Test 1 : retrieve empty Country
                [AbstractCache::STORED => [[
                    'ban',
                    999999999999,
                    'lapi-ban-country-AU',
                ]]],                            // Test 2 : retrieve ban for country
                [AbstractCache::STORED => [[
                    'captcha',
                    999999999999,
                    'lapi-captcha-country-AU',
                ]]]                             // Test 2 : retrieve ban for country
            )
        );

        // Test with null logger
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, null);

        // Test 1 (No cached items and no active decision)
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);

        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $result['remediation'],
            'Uncached (clean) and with no active decision should return a bypass remediation'
        );

        $adapter = $this->cacheStorage->getAdapter();
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_CACHE_KEY));
        $this->assertEquals(
            false,
            $item->isHit(),
            'Remediation should have not been cached'
        );
        // Test 2 (1 active decision for country)
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);

        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $result['remediation'],
            'Cached country ban should return ban'
        );

        // Test 3 (1 active decision for country (captcha) and 1 for ip (ban))
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);

        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $result['remediation'],
            'Should return higshest priority'
        );
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testRefreshDecisions($cacheType)
    {
        $this->setCache($cacheType);

        $remediationConfigs = [];

        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, $this->logger);

        $this->assertEquals($this->bouncer, $remediation->getClient());

        // Prepare next tests
        $this->bouncer->method('getStreamDecisions')->will(
            $this->onConsecutiveCalls(
                MockedData::DECISIONS['new_ip_v4'],            // Test 1 : new IP decision (ban)
                MockedData::DECISIONS['new_ip_v4'],            // Test 2 : same IP decision (ban)
                MockedData::DECISIONS['deleted_ip_v4'],        // Test 3 : deleted IP decision (existing one and not)
                MockedData::DECISIONS['new_ip_v4_range'],      // Test 4 : new RANGE decision (ban)
                MockedData::DECISIONS['delete_ip_v4_range'],   // Test 5 : deleted RANGE decision
                MockedData::DECISIONS['ip_v4_multiple'],       // Test 6 : retrieve multiple RANGE and IP decision
                MockedData::DECISIONS['ip_v4_multiple_bis'],   // Test 7 : retrieve multiple new and delete
                MockedData::DECISIONS['ip_v4_remove_unknown'], // Test 8 : delete unknown scope
                MockedData::DECISIONS['ip_v4_store_unknown'],  // Test 9 : store unknown scope
                MockedData::DECISIONS['new_ip_v6_range'],  // Test 10 : store IP V6 range
                MockedData::DECISIONS['country_ban']       // Test 11 : store country decision
            )
        );
        $this->bouncer->expects($this->exactly(11))
            ->method('getStreamDecisions')
            ->withConsecutive(
                [true, ['scopes' => 'ip,range']],
                [false, ['scopes' => 'ip,range']],
                [false, ['scopes' => 'ip,range']],
                [false, ['scopes' => 'ip,range']],
                [false, ['scopes' => 'ip,range']],
                [false, ['scopes' => 'ip,range']],
                [false, ['scopes' => 'ip,range']],
                [false, ['scopes' => 'ip,range']],
                [false, ['scopes' => 'ip,range']],
                [false, ['scopes' => 'ip,range']],
                [false, ['scopes' => 'ip,range,country']]
            );

        $this->assertEquals(
            false,
            file_exists($this->root->url() . '/' . $this->prodFile),
            'Prod File should not exist'
        );
        $item = $this->cacheStorage->getItem(AbstractCache::CONFIG);
        $this->assertEquals(
            false,
            $item->isHit(),
            'Cached should not be warmed up'
        );
        // Test 1
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 1, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        $item = $this->cacheStorage->getItem(AbstractCache::CONFIG);
        $this->assertEquals(
            true,
            $item->isHit(),
            'Cached should be warmed up'
        );
        $this->assertEqualsWithDelta(
            [
                AbstractCache::WARMUP => true,
                AbstractCache::FIRST_LAPI_CALL => time(),
            ],
            $item->get(),
            1000, // 1 second delta to avoid false negative
            'Config cache item should be as expected'
        );

        $adapter = $this->cacheStorage->getAdapter();
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_2_CACHE_KEY));
        $this->assertEquals(
            true,
            $item->isHit(),
            'Remediation should have been cached'
        );
        $cachedValue = $item->get();
        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $cachedValue[0][AbstractCache::INDEX_MAIN],
            'Remediation should have been cached with correct value'
        );
        // Test 2
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_2_CACHE_KEY));
        $this->assertEquals(
            true,
            $item->isHit(),
            'Remediation should still be cached'
        );
        // Test 3
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 1],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_2_CACHE_KEY));
        $this->assertEquals(
            false,
            $item->isHit(),
            'Remediation should have been deleted'
        );
        // Test 4
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 1, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(
            base64_encode(TestConstants::IP_V4_RANGE_CACHE_KEY)
        );
        $this->assertEquals(
            true,
            $item->isHit(),
            'Remediation should have been cached'
        );
        $item = $adapter->getItem(
            base64_encode(
                TestConstants::IP_V4_BUCKET_CACHE_KEY)
        );
        $this->assertEquals(
            true,
            $item->isHit(),
            'Range bucket should have been cached'
        );
        // Test 5
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 1],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(
            base64_encode(TestConstants::IP_V4_RANGE_CACHE_KEY)
        );
        $this->assertEquals(
            false,
            $item->isHit(),
            'Remediation should have been deleted'
        );
        $item = $adapter->getItem(
            base64_encode(
                TestConstants::IP_V4_BUCKET_CACHE_KEY)
        );
        $this->assertEquals(
            false,
            $item->isHit(),
            'Range bucket should have been deleted'
        );
        // Test 6
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 5, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_CACHE_KEY));
        $cachedValue = $item->get();
        $this->assertEquals(
            2,
            count($cachedValue),
            'Should have cached 2 remediations'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_2_CACHE_KEY));
        $cachedValue = $item->get();
        $this->assertEquals(
            1,
            count($cachedValue),
            'Should have cached 1 remediation'
        );
        // Test 7
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 1, 'deleted' => 1],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_CACHE_KEY));
        $cachedValue = $item->get();
        $this->assertEquals(
            1,
            count($cachedValue),
            'Should stay 1 cached remediation'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_2_CACHE_KEY));
        $cachedValue = $item->get();
        $this->assertEquals(
            2,
            count($cachedValue),
            'Should now have 2 cached remediation'
        );

        // Test 8
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        $this->assertEquals(
            true,
            file_exists($this->root->url() . '/' . $this->prodFile),
            'Prod File should  exist'
        );

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*"type":"REM_CACHE_REMOVE_NON_IMPLEMENTED_SCOPE.*capi-ban-do-not-know-delete-1.2.3.4"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 9
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*"type":"REM_CACHE_STORE_NON_IMPLEMENTED_SCOPE.*capi-ban-do-not-know-store-1.2.3.4"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 10
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*"type":"REM_CACHE_IPV6_RANGE_NOT_IMPLEMENTED"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 11
        $remediationConfigs = ['geolocation' => ['enabled' => true]];
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, $this->logger);
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 1, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(
            base64_encode(Constants::SCOPE_COUNTRY . AbstractCache::SEP . 'FR')
        );
        $this->assertEquals(
            true,
            $item->isHit(),
            'Remediation should have been cached for country'
        );
        // Test 12 (stream mode)
        $remediationConfigs = ['stream_mode' => false];
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, $this->logger);
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*200.*Decisions refresh is only available in stream mode.*"type":"LAPI_REM_REFRESH_DECISIONS"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );

        // parseDurationToSeconds
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'parseDurationToSeconds',
            ['1h']
        );
        $this->assertEquals(
            3600,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $remediation,
            'parseDurationToSeconds',
            ['147h']
        );
        $this->assertEquals(
            3600 * 147,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $remediation,
            'parseDurationToSeconds',
            ['147h23m43s']
        );
        $this->assertEquals(
            3600 * 147 + 23 * 60 + 43,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $remediation,
            'parseDurationToSeconds',
            ['23m43s']
        );
        $this->assertEquals(
            23 * 60 + 43,
            $result,
            'Should convert in seconds'
        );
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'parseDurationToSeconds',
            ['-23m43s']
        );
        $this->assertEquals(
            -23 * 60 - 43,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $remediation,
            'parseDurationToSeconds',
            ['2h15m123456ms']
        );
        $this->assertEquals(
            8223,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $remediation,
            'parseDurationToSeconds',
            ['1h45m30.123456s']
        );
        $this->assertEquals(
            6330,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $remediation,
            'parseDurationToSeconds',
            ['abc']
        );
        $this->assertEquals(
            0,
            $result,
            'Should return 0 on bad format'
        );
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*400.*"type":"REM_DECISION_DURATION_PARSE_ERROR"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
    }

    public function testPrivateOrProtectedMethods()
    {
        $cachePhpfilesConfigs = ['fs_cache_path' => $this->root->url()];
        $mockedMethods = [];
        $this->cacheStorage = $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs, $this->logger, $mockedMethods);
        $remediationConfigs = [];
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, $this->logger);
        // convertRawDecisionsToDecisions
        // Test 1 : ok
        $rawDecisions = [
            [
                'scope' => 'IP',
                'value' => '1.2.3.4',
                'type' => 'ban',
                'origin' => 'unit',
                'duration' => '147h',
            ],
        ];
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'convertRawDecisionsToDecisions',
            [$rawDecisions]
        );

        $this->assertCount(
            1,
            $result,
            'Should return array'
        );

        $decision = $result[0];
        $this->assertEquals(
            'ban',
            $decision->getType(),
            'Should have created a correct decision'
        );
        $this->assertEquals(
            'ip',
            $decision->getScope(),
            'Should have created a normalized scope'
        );
        // Test 2: bad raw decision
        $rawDecisions = [
            [
                'value' => '1.2.3.4',
                'origin' => 'unit',
                'duration' => '147h',
            ],
        ];
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'convertRawDecisionsToDecisions',
            [$rawDecisions]
        );
        $this->assertCount(
            0,
            $result,
            'Should return empty array'
        );

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*400.*"type":"REM_RAW_DECISION_NOT_AS_EXPECTED"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 3 : with id
        $rawDecisions = [
            [
                'scope' => 'IP',
                'value' => '1.2.3.4',
                'type' => 'ban',
                'origin' => 'unit',
                'duration' => '147h',
                'id' => 42,
            ],
        ];
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'convertRawDecisionsToDecisions',
            [$rawDecisions]
        );

        $this->assertCount(
            1,
            $result,
            'Should return array'
        );

        $decision = $result[0];
        $this->assertEquals(
            'unit-ban-ip-1.2.3.4',
            $decision->getIdentifier(),
            'Should have created a correct decision even with id'
        );
        // Test 4: bad raw decision for lists
        $rawDecisions = [
            [
                'value' => '1.2.3.4',
                'origin' => 'lists',
                'duration' => '147h',
                'type' => 'ban',
                'scope' => 'ip',
            ],
        ];
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'convertRawDecisionsToDecisions',
            [$rawDecisions]
        );
        $this->assertCount(
            0,
            $result,
            'Should return empty array'
        );

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*400.*"type":"REM_RAW_DECISION_NOT_AS_EXPECTED"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 5 : with lists and scenario
        $rawDecisions = [
            [
                'scope' => 'IP',
                'value' => '1.2.3.4',
                'type' => 'ban',
                'origin' => 'lists',
                'scenario' => 'crowdsec_proxy',
                'duration' => '147h',
            ],
        ];
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'convertRawDecisionsToDecisions',
            [$rawDecisions]
        );

        $this->assertCount(
            1,
            $result,
            'Should return array'
        );

        $decision = $result[0];
        $this->assertEquals(
            'lists:crowdsec_proxy-ban-ip-1.2.3.4',
            $decision->getIdentifier(),
            'Should have created a correct decision even with lists'
        );
        $this->assertEquals(
            'lists:crowdsec_proxy',
            $decision->getOrigin(),
            'Should have created a correct decision origin'
        );
        // capRemediationLevel
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'capRemediationLevel',
            ['ban']
        );
        $this->assertEquals('ban', $result, 'Remediation should be capped as ban');

        $remediationConfigs = ['bouncing_level' => Constants::BOUNCING_LEVEL_DISABLED];
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, $this->logger);

        $result = PHPUnitUtil::callMethod(
            $remediation,
            'capRemediationLevel',
            ['ban']
        );
        $this->assertEquals('bypass', $result, 'Remediation should be capped as bypass');

        $remediationConfigs = ['bouncing_level' => Constants::BOUNCING_LEVEL_FLEX];
        $remediation = new LapiRemediation($remediationConfigs, $this->bouncer, $this->cacheStorage, $this->logger);

        $result = PHPUnitUtil::callMethod(
            $remediation,
            'capRemediationLevel',
            ['ban']
        );
        $this->assertEquals('captcha', $result, 'Remediation should be capped as captcha');
    }

    protected function tearDown(): void
    {
        $this->cacheStorage->clear();
    }

    private function setCache(string $type)
    {
        switch ($type) {
            case 'PhpFilesAdapter':
                $this->cacheStorage = $this->phpFileStorage;
                break;
            case 'PhpFilesAdapterWithTags':
                $this->cacheStorage = $this->phpFileStorageWithTags;
                break;
            case 'RedisAdapterWithTags':
                $this->cacheStorage = $this->redisStorageWithTags;
                break;
            case 'RedisAdapter':
                $this->cacheStorage = $this->redisStorage;
                break;
            case 'MemcachedAdapter':
                $this->cacheStorage = $this->memcachedStorage;
                break;
            default:
                throw new \Exception('Unknown $type:' . $type);
        }
    }
}
