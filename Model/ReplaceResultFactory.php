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

class ReplaceResultFactory
{
    /**
     * Create.
     *
     * @param array $data
     * @return ReplaceResult
     */
    public function create(array $data = []): ReplaceResult
    {
        return new ReplaceResult($data);
    }
}
