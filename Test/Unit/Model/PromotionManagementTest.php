<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Test\Unit\Model;

use Itc\Promotion\Api\PromotionProductRepositoryInterface;
use Itc\Promotion\Model\Cache\PromotionListCache;
use Itc\Promotion\Model\Catalog\ExistingSkuChecker;
use Itc\Promotion\Model\Config\ModuleConfig;
use Itc\Promotion\Model\PromotionManagement;
use Itc\Promotion\Model\ReplaceResultFactory;
use Itc\Promotion\Model\Resolver\StorefrontProductResolver;
use Itc\Promotion\Model\StorefrontProductListFactory;
use Itc\Promotion\Model\Validator\SkuListValidator;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Webapi\Exception as WebapiException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PromotionManagementTest extends TestCase
{
    /**
     * @var ModuleConfig|MockObject
     */
    private ModuleConfig|MockObject $config;
    /**
     * @var ExistingSkuChecker|MockObject
     */
    private ExistingSkuChecker|MockObject $skuChecker;
    /**
     * @var PromotionProductRepositoryInterface|MockObject
     */
    private PromotionProductRepositoryInterface|MockObject $repository;
    /**
     * @var PromotionListCache|MockObject
     */
    private PromotionListCache|MockObject $cache;
    /**
     * @var StorefrontProductResolver|MockObject
     */
    private StorefrontProductResolver|MockObject $resolver;
    /**
     * @var PromotionManagement
     */
    private PromotionManagement $management;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(ModuleConfig::class);
        $this->skuChecker = $this->createMock(ExistingSkuChecker::class);
        $this->repository = $this->createMock(PromotionProductRepositoryInterface::class);
        $this->cache = $this->createMock(PromotionListCache::class);
        $this->resolver = $this->createMock(StorefrontProductResolver::class);
        $this->config->method('isEnabled')->willReturn(true);

        $this->management = new PromotionManagement(
            $this->config,
            new SkuListValidator(),
            $this->skuChecker,
            $this->repository,
            $this->cache,
            $this->resolver,
            new StorefrontProductListFactory(),
            new ReplaceResultFactory()
        );
    }

    /**
     * Verifies successful Update With One Sku.
     *
     * @return void
     */
    public function testSuccessfulUpdateWithOneSku(): void
    {
        $this->skuChecker->method('canonicalize')->willReturnArgument(0);
        $this->repository->expects($this->once())->method('replaceAll')->with(['PROMO-001']);
        $this->cache->expects($this->once())->method('invalidate');

        $result = $this->management->replaceBySkus(['PROMO-001']);
        $this->assertSame(['PROMO-001'], $result->getSkus());
        $this->assertSame(1, $result->getCount());
    }

    /**
     * Verifies successful Update With Five Skus Preserves Order.
     *
     * @return void
     */
    public function testSuccessfulUpdateWithFiveSkusPreservesOrder(): void
    {
        $skus = ['P1', 'P2', 'P3', 'P4', 'P5'];
        $this->skuChecker->method('canonicalize')->willReturnArgument(0);
        $this->repository->expects($this->once())->method('replaceAll')->with($skus);
        $this->cache->expects($this->once())->method('invalidate');
        $this->assertSame($skus, $this->management->replaceBySkus($skus)->getSkus());
    }

    /**
     * Verifies empty List Clears Promotions.
     *
     * @return void
     */
    public function testEmptyListClearsPromotions(): void
    {
        $this->skuChecker->expects($this->once())->method('canonicalize')->with([])->willReturn([]);
        $this->repository->expects($this->once())->method('replaceAll')->with([]);
        $this->cache->expects($this->once())->method('invalidate');
        $this->assertSame(0, $this->management->replaceBySkus([])->getCount());
    }

    /**
     * Verifies six Skus Do Not Change The Stored List.
     *
     * @return void
     */
    public function testSixSkusDoNotChangeTheStoredList(): void
    {
        $this->repository->expects($this->never())->method('modify');
        $this->repository->expects($this->never())->method('replaceAll');
        $this->cache->expects($this->never())->method('invalidate');
        $this->expectException(InputException::class);
        $this->management->replaceBySkus(['A', 'B', 'C', 'D', 'E', 'F']);
    }

    /**
     * Verifies duplicate Skus Do Not Change The Stored List.
     *
     * @return void
     */
    public function testDuplicateSkusDoNotChangeTheStoredList(): void
    {
        $this->repository->expects($this->never())->method('modify');
        $this->repository->expects($this->never())->method('replaceAll');
        $this->cache->expects($this->never())->method('invalidate');
        $this->expectException(InputException::class);
        $this->management->replaceBySkus(['A', 'A']);
    }

    /**
     * Verifies missing Sku Does Not Change The Stored List.
     *
     * @return void
     */
    public function testMissingSkuDoesNotChangeTheStoredList(): void
    {
        $this->skuChecker->method('canonicalize')->willThrowException(
            new InputException(__('These SKUs do not exist: MISSING'))
        );
        $this->repository->expects($this->never())->method('modify');
        $this->repository->expects($this->never())->method('replaceAll');
        $this->cache->expects($this->never())->method('invalidate');
        $this->expectException(InputException::class);
        $this->management->replaceBySkus(['MISSING']);
    }

    /**
     * Verifies a differently cased request is stored as the catalog SKU.
     *
     * @return void
     */
    public function testPutPersistsTheCanonicalCatalogSku(): void
    {
        $this->skuChecker->method('canonicalize')->with(['24-mb01'])->willReturn(['24-MB01']);
        $this->repository->expects($this->once())->method('replaceAll')->with(['24-MB01']);
        $result = $this->management->replaceBySkus(['24-mb01']);
        $this->assertSame(['24-MB01'], $result->getSkus());
    }

    /**
     * Verifies mixed-case duplicates are rejected before the lock.
     *
     * @return void
     */
    public function testCanonicalDuplicatesDoNotChangeTheStoredList(): void
    {
        $this->skuChecker->method('canonicalize')->willReturn(['24-MB01', '24-MB01']);
        $this->repository->expects($this->never())->method('modify');
        $this->repository->expects($this->never())->method('replaceAll');
        $this->cache->expects($this->never())->method('invalidate');
        $this->expectException(InputException::class);
        $this->management->replaceBySkus(['24-mb01', '24-MB01']);
    }

    /**
     * Verifies disabled Module Rejects Updates.
     *
     * @return void
     */
    public function testDisabledModuleRejectsUpdates(): void
    {
        $config = $this->createMock(ModuleConfig::class);
        $config->method('isEnabled')->willReturn(false);
        $management = new PromotionManagement(
            $config,
            new SkuListValidator(),
            $this->skuChecker,
            $this->repository,
            $this->cache,
            $this->resolver,
            new StorefrontProductListFactory(),
            new ReplaceResultFactory()
        );
        $this->repository->expects($this->never())->method('modify');
        $this->repository->expects($this->never())->method('replaceAll');
        try {
            $management->replaceBySkus(['A']);
            $this->fail('Disabled module must reject updates.');
        } catch (WebapiException $exception) {
            $this->assertSame(503, $exception->getHttpCode());
        }
    }

    /**
     * Verifies disabled Module Returns An Empty Public List.
     *
     * @return void
     */
    public function testDisabledModuleReturnsAnEmptyPublicList(): void
    {
        $config = $this->createMock(ModuleConfig::class);
        $config->method('isEnabled')->willReturn(false);
        $management = new PromotionManagement(
            $config,
            new SkuListValidator(),
            $this->skuChecker,
            $this->repository,
            $this->cache,
            $this->resolver,
            new StorefrontProductListFactory(),
            new ReplaceResultFactory()
        );
        $this->repository->expects($this->never())->method('getOrderedSkus');
        $this->resolver->expects($this->never())->method('resolve');
        $this->assertSame([], $management->getStorefrontProducts()->getItems());
    }

    /**
     * Verifies admin Add Invalidates Cache Through The Same Replace Service.
     *
     * @return void
     */
    public function testAdminAddInvalidatesCacheThroughTheSameReplaceService(): void
    {
        $this->skuChecker->method('canonicalize')->willReturnArgument(0);
        $this->repository->expects($this->once())->method('modify')->willReturnCallback(
            static fn (callable $mutator): array => array_values($mutator(['A']))
        );
        $this->repository->expects($this->never())->method('getOrderedSkus');
        $this->repository->expects($this->never())->method('replaceAll');
        $this->cache->expects($this->once())->method('invalidate');
        $result = $this->management->addProduct('B');
        $this->assertSame(['A', 'B'], $result->getSkus());
    }

    /**
     * Verifies an Admin add does not read or write outside the repository modify().
     *
     * @return void
     */
    public function testAdminAddReadsTheListInsideTheLock(): void
    {
        $this->skuChecker->method('canonicalize')->willReturnArgument(0);
        $this->repository->expects($this->once())->method('modify')->willReturnCallback(
            static fn (callable $mutator): array => array_values($mutator(['A', 'B', 'C']))
        );
        $this->repository->expects($this->never())->method('getOrderedSkus');
        $this->repository->expects($this->never())->method('replaceAll');

        $result = $this->management->addProduct('D');
        $this->assertSame(['A', 'B', 'C', 'D'], $result->getSkus());
    }

    /**
     * Verifies several SKUs are inserted in one locked write, left to right.
     *
     * @return void
     */
    public function testAddProductsInsertsTheBatchInOneWrite(): void
    {
        $this->skuChecker->method('canonicalize')->willReturnArgument(0);
        $this->repository->expects($this->once())->method('modify')->willReturnCallback(
            static fn (callable $mutator): array => array_values($mutator(['A']))
        );
        $this->cache->expects($this->once())->method('invalidate');

        $result = $this->management->addProducts(['B', 'C'], [0, null]);

        $this->assertSame(['B', 'A', 'C'], $result->getSkus());
    }

    /**
     * Verifies a second add blocked by the lock cannot overwrite the first.
     *
     * @return void
     */
    public function testOverlappingAddKeepsBothSkusAfterTheLockIsReleased(): void
    {
        $store = new class implements PromotionProductRepositoryInterface {
            /** @var string[] */
            public array $skus = ['A', 'B', 'C'];
            /** @var bool */
            public bool $locked = false;
            /** @var int */
            public int $rejectedWhileLocked = 0;
            /** @var \Closure|null */
            public ?\Closure $duringReplace = null;

            public function getOrderedSkus(): array
            {
                return $this->skus;
            }

            public function replaceAll(array $skus): void
            {
                $this->skus = array_values($skus);
            }

            public function modify(callable $mutator): array
            {
                if ($this->locked) {
                    $this->rejectedWhileLocked++;
                    throw new CouldNotSaveException(__('Could not lock the promotion list for update.'));
                }
                $this->locked = true;
                try {
                    $next = array_values($mutator($this->skus));
                    $this->skus = $next;
                    if ($this->duringReplace !== null) {
                        $hook = $this->duringReplace;
                        $this->duringReplace = null;
                        $hook();
                    }
                    return $next;
                } finally {
                    $this->locked = false;
                }
            }

            public function assignPosition(string $sku, int $position): void
            {
            }

            public function getItems(): array
            {
                return [];
            }
        };
        $checker = $this->createMock(ExistingSkuChecker::class);
        $checker->method('canonicalize')->willReturnArgument(0);
        $cache = $this->createMock(PromotionListCache::class);
        $management = new PromotionManagement(
            $this->config,
            new SkuListValidator(),
            $checker,
            $store,
            $cache,
            $this->resolver,
            new StorefrontProductListFactory(),
            new ReplaceResultFactory()
        );
        $store->duringReplace = static function () use ($management): void {
            try {
                $management->addProduct('E');
            } catch (CouldNotSaveException) {
                return;
            }
        };

        $management->addProduct('D');
        $this->assertSame(1, $store->rejectedWhileLocked);
        $this->assertSame(['A', 'B', 'C', 'D'], $store->skus);
        $this->assertFalse($store->locked);

        $management->addProduct('E');
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], $store->skus);
        $this->assertCount(5, array_unique($store->skus));
    }

    /**
     * Verifies an inline position is stored as entered and the other rows stay put.
     *
     * @return void
     */
    public function testMoveProductKeepsTheEnteredPosition(): void
    {
        $this->repository->expects($this->once())->method('assignPosition')->with('24-MB01', 15);
        $this->repository->expects($this->once())->method('getItems')->willReturn([
            ['sku' => '24-MB04', 'position' => 0],
            ['sku' => '24-MB03', 'position' => 1],
            ['sku' => '24-MB01', 'position' => 15],
        ]);
        $this->repository->expects($this->never())->method('modify');
        $this->cache->expects($this->once())->method('invalidate');

        $result = $this->management->moveProduct('24-MB01', 15);

        $this->assertSame(['24-MB04', '24-MB03', '24-MB01'], $result->getSkus());
    }

    /**
     * Verifies an exception inside the lock releases it and keeps the previous rows.
     *
     * @return void
     */
    public function testFailedChangeReleasesTheLockWithoutWriting(): void
    {
        $store = new class implements PromotionProductRepositoryInterface {
            /** @var string[] */
            public array $skus = ['24-MB01', '24-MB04', '24-MB03'];
            /** @var bool */
            public bool $locked = false;
            /** @var int */
            public int $writes = 0;

            public function getOrderedSkus(): array
            {
                return $this->skus;
            }

            public function replaceAll(array $skus): void
            {
                $this->writes++;
                $this->skus = array_values($skus);
            }

            public function modify(callable $mutator): array
            {
                $this->locked = true;
                try {
                    $next = array_values($mutator($this->skus));
                    $this->writes++;
                    $this->skus = $next;
                    return $next;
                } finally {
                    $this->locked = false;
                }
            }

            public function assignPosition(string $sku, int $position): void
            {
            }

            public function getItems(): array
            {
                return [];
            }
        };
        $checker = $this->createMock(ExistingSkuChecker::class);
        $checker->method('canonicalize')->willThrowException(
            new InputException(__('These SKUs do not exist: NOPE'))
        );
        $cache = $this->createMock(PromotionListCache::class);
        $cache->expects($this->never())->method('invalidate');
        $management = new PromotionManagement(
            $this->config,
            new SkuListValidator(),
            $checker,
            $store,
            $cache,
            $this->resolver,
            new StorefrontProductListFactory(),
            new ReplaceResultFactory()
        );

        try {
            $management->changeProduct('24-MB01', 'NOPE', 0);
            $this->fail('Unknown SKU must be rejected.');
        } catch (InputException $exception) {
            $this->assertStringContainsString('NOPE', $exception->getMessage());
        }
        $this->assertFalse($store->locked);
        $this->assertSame(0, $store->writes);
        $this->assertSame(['24-MB01', '24-MB04', '24-MB03'], $store->skus);
    }

    /**
     * Verifies editing a row replaces that SKU and keeps the other rows in order.
     *
     * @return void
     */
    public function testChangeProductReplacesSkuWithoutDroppingTheRestOfTheList(): void
    {
        $this->repository->method('modify')->willReturnCallback(
            static fn (callable $mutator): array => array_values(
                $mutator(['24-MB01', '24-MB04', '24-MB03'])
            )
        );
        $this->repository->expects($this->never())->method('getOrderedSkus');
        $this->repository->expects($this->never())->method('replaceAll');
        $this->skuChecker->method('canonicalize')->with(['24-wb02'])->willReturn(['24-WB02']);
        $this->cache->expects($this->once())->method('invalidate');

        $result = $this->management->changeProduct('24-mb01', '24-wb02', 0);
        $this->assertSame(['24-WB02', '24-MB04', '24-MB03'], $result->getSkus());
        $this->assertNotContains('24-MB01', $result->getSkus());
    }

    /**
     * Verifies a replacement that names an existing row does not write.
     *
     * @return void
     */
    public function testChangeProductRejectsADuplicateCanonicalSku(): void
    {
        $this->repository->method('modify')->willReturnCallback(
            static fn (callable $mutator): array => array_values($mutator(['24-MB01', '24-MB04']))
        );
        $this->skuChecker->method('canonicalize')->with(['24-mb04'])->willReturn(['24-MB04']);
        $this->repository->expects($this->never())->method('replaceAll');
        $this->cache->expects($this->never())->method('invalidate');
        $this->expectException(InputException::class);
        $this->management->changeProduct('24-MB01', '24-mb04', 0);
    }

    /**
     * Verifies remove uses the locked list and matches a different request casing.
     *
     * @return void
     */
    public function testRemoveMatchesCanonicalCasingFromTheLockedList(): void
    {
        $this->repository->expects($this->once())->method('modify')->willReturnCallback(
            static fn (callable $mutator): array => array_values($mutator(['24-MB01', '24-MB04']))
        );
        $this->repository->expects($this->never())->method('replaceAll');
        $result = $this->management->removeProduct('24-mb01');
        $this->assertSame(['24-MB04'], $result->getSkus());
    }
}
