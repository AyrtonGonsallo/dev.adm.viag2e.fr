<?php
namespace App\Twig;

use Twig\Loader\LoaderInterface;
use Twig\Source;

class StringLoader implements LoaderInterface
{
    /**
     * @inheritdoc
     */
    public function getSource($name)
    {
        return $name;
    }

    /**
     * @inheritDoc
     */
    public function getSourceContext(string $name): Source
    {
        return new Source($name, $name);
    }

    /**
     * @inheritdoc
     */
    public function getCacheKey(string $name): string
    {
        return $name;
    }

    /**
     * @inheritdoc
     */
    public function isFresh(string $name, int $time): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $name)
    {
        return preg_match('/\s/', $name);
    }
}
