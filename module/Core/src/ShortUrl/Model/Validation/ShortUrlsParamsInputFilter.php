<?php

declare(strict_types=1);

namespace Shlinkio\Shlink\Core\ShortUrl\Model\Validation;

use Laminas\InputFilter\InputFilter;
use Laminas\Validator\InArray;
use Shlinkio\Shlink\Common\Paginator\Paginator;
use Shlinkio\Shlink\Common\Validation;
use Shlinkio\Shlink\Core\ShortUrl\Model\OrderableField;
use Shlinkio\Shlink\Core\ShortUrl\Model\TagsMode;

use function Shlinkio\Shlink\Core\enumValues;

class ShortUrlsParamsInputFilter extends InputFilter
{
    use Validation\InputFactoryTrait;

    public const PAGE = 'page';
    public const SEARCH_TERM = 'searchTerm';
    public const TAGS = 'tags';
    public const START_DATE = 'startDate';
    public const END_DATE = 'endDate';
    public const ITEMS_PER_PAGE = 'itemsPerPage';
    public const TAGS_MODE = 'tagsMode';
    public const ORDER_BY = 'orderBy';
    public const EXCLUDE_MAX_VISITS_REACHED = 'excludeMaxVisitsReached';
    public const EXCLUDE_PAST_VALID_UNTIL = 'excludePastValidUntil';

    public function __construct(array $data)
    {
        $this->initialize();
        $this->setData($data);
    }

    private function initialize(): void
    {
        $this->add($this->createDateInput(self::START_DATE, false));
        $this->add($this->createDateInput(self::END_DATE, false));

        $this->add($this->createInput(self::SEARCH_TERM, false));

        $this->add($this->createNumericInput(self::PAGE, false));
        $this->add($this->createNumericInput(self::ITEMS_PER_PAGE, false, Paginator::ALL_ITEMS));

        $this->add($this->createTagsInput(self::TAGS, false));

        $tagsMode = $this->createInput(self::TAGS_MODE, false);
        $tagsMode->getValidatorChain()->attach(new InArray([
            'haystack' => enumValues(TagsMode::class),
            'strict' => InArray::COMPARE_STRICT,
        ]));
        $this->add($tagsMode);

        $this->add($this->createOrderByInput(self::ORDER_BY, enumValues(OrderableField::class)));

        $this->add($this->createBooleanInput(self::EXCLUDE_MAX_VISITS_REACHED, false));
        $this->add($this->createBooleanInput(self::EXCLUDE_PAST_VALID_UNTIL, false));
    }
}
