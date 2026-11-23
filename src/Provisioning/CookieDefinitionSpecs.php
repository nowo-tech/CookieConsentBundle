<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

use function array_is_list;
use function array_values;
use function count;
use function get_debug_type;
use function is_array;
use function sprintf;

/**
 * The cookie definitions {@see CookieConsentProvisionerInterface::upsertCookieDefinitions()} writes (1.36): an
 * immutable list of {@see CookieDefinitionSpec}, one per cookie name (names compared byte for byte, `===`).
 *
 * @implements IteratorAggregate<int, CookieDefinitionSpec>
 */
final class CookieDefinitionSpecs implements Countable, IteratorAggregate
{
    /** @var array<string, CookieDefinitionSpec> by name (keys prefixed, so a numeric name stays a string) */
    private array $definitions = [];

    /**
     * @throws InvalidProvisioningSpecException When a cookie name is given twice
     */
    public function __construct(CookieDefinitionSpec ...$definitions)
    {
        foreach ($definitions as $definition) {
            $key = 'n:' . $definition->name;
            if (isset($this->definitions[$key])) {
                throw new InvalidProvisioningSpecException(sprintf('The cookie definition "%s" is given twice.', $definition->name));
            }

            $this->definitions[$key] = $definition;
        }
    }

    /**
     * Builds the list from a decoded fixture: a list of `cookie_inventory`-shaped entries
     * (see {@see CookieDefinitionSpec::fromArray()}).
     *
     * @param array<mixed> $entries
     *
     * @throws InvalidProvisioningSpecException
     */
    public static function fromArray(array $entries): self
    {
        if (!array_is_list($entries)) {
            throw new InvalidProvisioningSpecException('The cookie definitions must be a list.');
        }

        $definitions = [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                throw new InvalidProvisioningSpecException(sprintf('Cookie definition #%d must be an object, got %s.', $index, get_debug_type($entry)));
            }

            $definitions[] = CookieDefinitionSpec::fromArray($entry);
        }

        return new self(...$definitions);
    }

    /**
     * The cookie definitions in order.
     *
     * @return list<CookieDefinitionSpec>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /**
     * Number of cookie definitions.
     */
    public function count(): int
    {
        return count($this->definitions);
    }

    /**
     * Iterates over the cookie definitions in order.
     *
     * @return Traversable<int, CookieDefinitionSpec>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->all());
    }
}
