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

use Itc\Promotion\Model\PromotionProductRepository;
use Itc\Promotion\Model\ResourceModel\PromotionItem as PromotionItemResource;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PromotionProductRepositoryTest extends TestCase
{
    /**
     * Verifies replaceAll locks the write and stores SKUs in request order.
     *
     * @return void
     */
    public function testReplaceAllWritesSkusInOrderUnderOneLock(): void
    {
        $connection = $this->connection();
        $written = [];
        $connection->expects($this->once())->method('fetchOne')->willReturn('1');
        $connection->expects($this->once())->method('query');
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('rollBack');
        $connection->expects($this->never())->method('fetchCol');
        $connection->method('insert')->willReturnCallback(
            static function (string $table, array $row) use (&$written): int {
                $written[] = $row['sku'];
                return 1;
            }
        );
        $this->repository($connection)->replaceAll(['24-MB01', '24-MB04']);
        $this->assertSame(['24-MB01', '24-MB04'], $written);
    }

    /**
     * Verifies a lock timeout does not start the transaction or release someone else's lock.
     *
     * @return void
     */
    public function testLockTimeoutDoesNotRunTheOperation(): void
    {
        $connection = $this->connection();
        $connection->expects($this->once())->method('fetchOne')->willReturn('0');
        $connection->expects($this->never())->method('query');
        $connection->expects($this->never())->method('beginTransaction');
        $connection->expects($this->never())->method('fetchCol');

        try {
            $this->repository($connection)->replaceAll(['24-MB01']);
            $this->fail('A missed lock must fail the operation.');
        } catch (CouldNotSaveException $exception) {
            $this->assertStringContainsString('lock', $exception->getMessage());
        }
    }

    /**
     * Verifies a failed insert rolls back and still releases the lock this call acquired.
     *
     * @return void
     */
    public function testFailedReplaceRollsBackAndReleasesTheLock(): void
    {
        $connection = $this->connection();
        $connection->expects($this->once())->method('fetchOne')->willReturn('1');
        $connection->expects($this->once())->method('query');
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('delete')->with('itc_promotion_item');
        $connection->method('insert')->willThrowException(new \RuntimeException('constraint'));
        $connection->expects($this->once())->method('rollBack');
        $connection->expects($this->never())->method('commit');

        $this->expectException(CouldNotSaveException::class);
        $this->repository($connection)->replaceAll(['24-MB01']);
    }

    /**
     * Verifies modify reads the latest rows and writes them under a single GET_LOCK.
     *
     * @return void
     */
    public function testModifyReadsAndWritesUnderOneLock(): void
    {
        $connection = $this->connection();
        $written = [];
        $connection->expects($this->once())->method('fetchOne')->willReturn('1');
        $connection->expects($this->once())->method('query');
        $connection->method('fetchCol')->willReturn(['SKU-A', 'SKU-B', 'SKU-C']);
        $connection->method('insert')->willReturnCallback(
            static function (string $table, array $row) use (&$written): int {
                $written[] = $row['sku'];
                return 1;
            }
        );

        $saved = $this->repository($connection)->modify(
            static fn (array $current): array => [...$current, 'SKU-D']
        );

        $this->assertSame(['SKU-A', 'SKU-B', 'SKU-C', 'SKU-D'], $saved);
        $this->assertSame(['SKU-A', 'SKU-B', 'SKU-C', 'SKU-D'], $written);
    }

    /**
     * Verifies a nested replaceAll on the same instance does not GET_LOCK again.
     *
     * @return void
     */
    public function testNestedReplaceDoesNotReacquireTheNamedLock(): void
    {
        $connection = $this->connection();
        $connection->expects($this->once())->method('fetchOne')->willReturn('1');
        $connection->expects($this->once())->method('query');
        $connection->method('fetchCol')->willReturn(['SKU-A']);
        $repository = $this->repository($connection);

        $repository->modify(function () use ($repository): array {
            $repository->replaceAll(['SKU-A', 'SKU-D']);
            return ['SKU-A', 'SKU-D'];
        });
    }

    /**
     * Verifies a second repository cannot save a stale snapshot while the first holds the lock.
     *
     * @return void
     */
    public function testSecondRepositoryCannotOverwriteALockedAdd(): void
    {
        $state = new \stdClass();
        $state->locked = false;
        $state->rows = ['SKU-A', 'SKU-B', 'SKU-C'];
        $state->getLockCalls = 0;
        $state->releaseCalls = 0;

        $connection = $this->connection();
        $connection->method('fetchOne')->willReturnCallback(
            static function (string $sql) use ($state): string {
                if (!str_contains($sql, 'GET_LOCK')) {
                    return '0';
                }
                $state->getLockCalls++;
                if ($state->locked) {
                    return '0';
                }
                $state->locked = true;
                return '1';
            }
        );
        $connection->method('query')->willReturnCallback(
            static function (string $sql) use ($state): void {
                if (str_contains($sql, 'RELEASE_LOCK')) {
                    $state->releaseCalls++;
                    $state->locked = false;
                }
            }
        );
        $connection->method('fetchCol')->willReturnCallback(
            static function () use ($state): array {
                return $state->rows;
            }
        );
        $connection->method('delete')->willReturnCallback(
            static function () use ($state): int {
                $state->rows = [];
                return 0;
            }
        );
        $connection->method('insert')->willReturnCallback(
            static function (string $table, array $row) use ($state): int {
                $state->rows[] = $row['sku'];
                return 1;
            }
        );

        $first = $this->repository($connection);
        $second = $this->repository($connection);

        $first->modify(function (array $current) use ($second, $state): array {
            $this->assertSame(['SKU-A', 'SKU-B', 'SKU-C'], $current);
            $this->assertTrue($state->locked);
            try {
                $second->modify(static fn (array $stale): array => [...$stale, 'SKU-E']);
                $this->fail('The overlapping add must not run while the lock is held.');
            } catch (CouldNotSaveException) {
                $this->assertTrue($state->locked);
                $this->assertSame(0, $state->releaseCalls);
            }
            return [...$current, 'SKU-D'];
        });

        $this->assertSame(['SKU-A', 'SKU-B', 'SKU-C', 'SKU-D'], $state->rows);
        $this->assertFalse($state->locked);
        $this->assertSame(1, $state->releaseCalls);

        $second->modify(static fn (array $current): array => [...$current, 'SKU-E']);

        $this->assertSame(['SKU-A', 'SKU-B', 'SKU-C', 'SKU-D', 'SKU-E'], $state->rows);
        $this->assertFalse($state->locked);
        $this->assertSame(3, $state->getLockCalls);
        $this->assertSame(2, $state->releaseCalls);
    }

    /**
     * Builds a connection double that quotes SQL without a database.
     *
     * @return AdapterInterface|MockObject
     */
    private function connection(): AdapterInterface|MockObject
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quoteInto')->willReturnArgument(0);
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $connection->method('select')->willReturn($select);
        return $connection;
    }

    /**
     * Builds the repository against the connection double.
     *
     * @param AdapterInterface $connection
     * @return PromotionProductRepository
     */
    private function repository(AdapterInterface $connection): PromotionProductRepository
    {
        $resource = $this->createMock(PromotionItemResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('itc_promotion_item');
        $date = $this->createMock(DateTime::class);
        $date->method('gmtDate')->willReturn('2026-01-01 00:00:00');
        $logger = $this->createMock(LoggerInterface::class);

        return new PromotionProductRepository($resource, $date, $logger);
    }
}
