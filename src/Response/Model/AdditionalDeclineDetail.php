<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Model;

use Academe\Opayo\Pi\Helper;
use JsonSerializable;

/**
 * The extended decline detail from the card scheme, returned with a declined
 * card transaction. Use it to decide whether the payment can be retried.
 */

class AdditionalDeclineDetail implements JsonSerializable
{
    // The categories known at the time of writing; more may be added.

    // Card declined: do not try again within 30 days.
    public const CATEGORY_DO_NOT_RETRY = '01';
    // The issuer cannot approve at this time: trying again is permitted.
    public const CATEGORY_RETRY_LATER = '02';
    // Incorrect or missing card data: correct it before trying again.
    public const CATEGORY_CORRECT_DATA = '03';
    // Trying again is permitted.
    public const CATEGORY_RETRY = '04';

    /**
     * @param string|null $code The additional decline code, e.g. "03", "R1", "N7"
     * @param string|null $description Description of the code; varies by acquiring bank
     * @param string|null $category The category of the decline, e.g. "01"
     */
    public function __construct(
        protected readonly ?string $code = null,
        protected readonly ?string $description = null,
        protected readonly ?string $category = null
    ) {
    }

    /**
     * @return string|null The additional decline code (additionalDeclineCode)
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @return string|null Description of the code (additionalDeclineCodeDescription)
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return string|null The category of the decline (additionalDeclineCodeCategory);
     *                     compare with the CATEGORY_* constants
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * Construct an instance from raw data.
     */
    public static function fromData(array|object|string $data): static
    {
        // For convenience.
        if (is_string($data)) {
            $data = json_decode($data);
        }

        // If the data is inside an "additionalDeclineDetail" wrapper then
        // remove it to make processing easier.
        if ($insideWrapper = Helper::dataGet($data, 'additionalDeclineDetail')) {
            $data = $insideWrapper;
        }

        return new static(
            Helper::dataGet($data, 'additionalDeclineCode'),
            Helper::dataGet($data, 'additionalDeclineCodeDescription'),
            Helper::dataGet($data, 'additionalDeclineCodeCategory')
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getData(): array
    {
        $detail = [];

        if (isset($this->code)) {
            $detail['additionalDeclineCode'] = $this->code;
        }

        if (isset($this->description)) {
            $detail['additionalDeclineCodeDescription'] = $this->description;
        }

        if (isset($this->category)) {
            $detail['additionalDeclineCodeCategory'] = $this->category;
        }

        return ['additionalDeclineDetail' => $detail];
    }

    public function jsonSerialize(): mixed
    {
        return $this->getData();
    }
}
