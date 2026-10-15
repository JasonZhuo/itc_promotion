<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Test\Unit\Model\Validator;

use Itc\Promotion\Model\Validator\SkuListValidator;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\TestCase;

class SkuListValidatorTest extends TestCase
{
    /**
     * @var SkuListValidator
     */
    private SkuListValidator $validator;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->validator = new SkuListValidator();
    }

    /**
     * Verifies empty List Is Valid.
     *
     * @return void
     */
    public function testEmptyListIsValid(): void
    {
        $this->assertSame([], $this->validator->normalize([]));
    }

    /**
     * Verifies five Skus Preserve Order.
     *
     * @return void
     */
    public function testFiveSkusPreserveOrder(): void
    {
        $skus = ['A', 'B', 'C', 'D', 'E'];
        $this->assertSame($skus, $this->validator->normalize($skus));
    }

    /**
     * Verifies six Skus Are Rejected.
     *
     * @return void
     */
    public function testSixSkusAreRejected(): void
    {
        $this->expectException(InputException::class);
        $this->validator->normalize(['A', 'B', 'C', 'D', 'E', 'F']);
    }

    /**
     * Verifies duplicate Skus Are Rejected.
     *
     * @return void
     */
    public function testDuplicateSkusAreRejected(): void
    {
        $this->expectException(InputException::class);
        $this->validator->normalize(['A', 'A']);
    }

    /**
     * Verifies malformed Sku Is Rejected.
     *
     * @return void
     */
    public function testMalformedSkuIsRejected(): void
    {
        $this->expectException(InputException::class);
        $this->validator->normalize(['ok', '   ']);
    }
}
