<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

final class Pages
{
    /**
     * Cut one page out of a list already filtered and ordered, as a repository in memory would.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return Page<T>
     */
    public static function of(array $items, PageRequest $request): Page
    {
        return new Page(array_slice($items, $request->offset(), $request->size), count($items), $request);
    }
}
