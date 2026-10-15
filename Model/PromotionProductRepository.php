<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model;

use Itc\Promotion\Api\PromotionProductRepositoryInterface;
use Itc\Promotion\Model\ResourceModel\PromotionItem as PromotionItemResource;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class PromotionProductRepository implements PromotionProductRepositoryInterface
{
    private const LOCK_NAME = 'itc_promotion_replace';
    private const LOCK_TIMEOUT_SECONDS = 10;

    /**
     * True only while this instance holds itc_promotion_replace.
     *
     * @var bool
     */
    private bool $lockHeld = false;

    /**
     * Constructor.
     *
     * @param PromotionItemResource $resource
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PromotionItemResource $resource,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getOrderedSkus(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getMainTable(), ['sku'])
            ->order('position ASC')
            ->order('entity_id ASC');
        $skus = [];
        foreach ($connection->fetchCol($select) as $sku) {
            $skus[] = (string) $sku;
        }
        return $skus;
    }

    /**
     * @inheritdoc
     */
    public function getItems(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getMainTable(), ['sku', 'position'])
            ->order('position ASC')
            ->order('entity_id ASC');
        $items = [];
        foreach ($connection->fetchAll($select) as $row) {
            $items[] = [
                'sku' => (string) $row['sku'],
                'position' => (int) $row['position'],
            ];
        }
        return $items;
    }

    /**
     * @inheritdoc
     */
    public function assignPosition(string $sku, int $position): void
    {
        $this->withLock(function () use ($sku, $position): void {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getMainTable();
            $connection->beginTransaction();
            try {
                $stored = $this->findStoredSku($sku);
                if ($stored === null) {
                    throw new InputException(__('SKU "%1" is not in the promotion list.', trim($sku)));
                }
                $connection->update(
                    $table,
                    [
                        'position' => $position,
                        'updated_at' => $this->dateTime->gmtDate(),
                    ],
                    ['sku = ?' => $stored]
                );
                $connection->commit();
            } catch (InputException $exception) {
                $connection->rollBack();
                throw $exception;
            } catch (\Throwable $exception) {
                $connection->rollBack();
                $this->logger->error('Itc_Promotion failed to update a promotion position.', [
                    'exception' => $exception,
                ]);
                throw new CouldNotSaveException(__('Could not save the promotion list.'), $exception);
            }
        });
    }

    /**
     * Finds the stored SKU without requiring the request to match catalog letter casing.
     *
     * @param string $sku
     * @return string|null
     */
    private function findStoredSku(string $sku): ?string
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getMainTable(), ['sku'])
            ->where('LOWER(sku) = ?', mb_strtolower(trim($sku), 'UTF-8'))
            ->limit(1);
        $stored = $connection->fetchOne($select);
        if ($stored === false || $stored === null || $stored === '') {
            return null;
        }
        return (string) $stored;
    }

    /**
     * @inheritdoc
     */
    public function replaceAll(array $skus): void
    {
        $this->withLock(function () use ($skus): void {
            $this->write($skus);
        });
    }

    /**
     * @inheritdoc
     */
    public function modify(callable $mutator): array
    {
        return $this->withLock(function () use ($mutator): array {
            $current = $this->getOrderedSkus();
            $next = array_values($mutator($current));
            $this->write($next);
            return $next;
        });
    }

    /**
     * Replaces the table inside a transaction. The caller already holds the lock.
     *
     * @param string[] $skus
     * @throws CouldNotSaveException
     */
    private function write(array $skus): void
    {
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $table = $this->resource->getMainTable();
            $connection->delete($table);
            $now = $this->dateTime->gmtDate();
            foreach (array_values($skus) as $position => $sku) {
                $connection->insert($table, [
                    'sku' => $sku,
                    'position' => $position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $this->logger->error('Itc_Promotion failed to replace the promotion list.', [
                'exception' => $exception,
            ]);
            throw new CouldNotSaveException(
                __('Could not save the promotion list.'),
                $exception
            );
        }
    }

    /**
     * Acquires the named lock once for this instance.
     *
     * A nested call does not issue another GET_LOCK and does not release early.
     *
     * @param callable $operation
     * @return mixed
     * @throws CouldNotSaveException
     */
    private function withLock(callable $operation)
    {
        if ($this->lockHeld) {
            return $operation();
        }

        $connection = $this->resource->getConnection();
        $lockSql = $connection->quoteInto(
            'SELECT GET_LOCK(?, ' . self::LOCK_TIMEOUT_SECONDS . ')',
            self::LOCK_NAME
        );
        $locked = (int) $connection->fetchOne($lockSql);
        if ($locked !== 1) {
            throw new CouldNotSaveException(__('Could not lock the promotion list for update.'));
        }

        $this->lockHeld = true;
        try {
            return $operation();
        } finally {
            $this->lockHeld = false;
            $connection->query($connection->quoteInto('SELECT RELEASE_LOCK(?)', self::LOCK_NAME));
        }
    }
}
