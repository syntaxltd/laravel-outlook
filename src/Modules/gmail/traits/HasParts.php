<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail\traits;

use Google_Service_Gmail_MessagePart;
use Illuminate\Support\Collection;

trait HasParts
{

    /**
     * LOL
     * @var Collection
     */
    private Collection $allParts;

    /**
     * Find all Parts of a message.
     * Necessary to reset the $allParts Varibale.
     *
     * @param Collection $partsContainer  . F.e. collect([$message->payload])
     *
     * @return Collection of all 'parts' flattened
     */
    private function getAllParts(Collection $partsContainer): Collection
    {
        $this->iterateParts($partsContainer);

        return collect($this->allParts);
    }


    /**
     * Recursive Method. Iterates through a collection,
     * finding all 'parts'.
     *
     * @param collection $partsContainer
     * @param bool $returnOnFirstFound
     *
     * @return boolean
     */

    private function iterateParts(Collection $partsContainer, bool $returnOnFirstFound = false): bool
    {
        $parts = [];

        $plucked = $partsContainer->flatten()->filter();

        if ($plucked->count()) {
            $parts = $plucked;
        } else {
            if ($partsContainer->count()) {
                $parts = $partsContainer;
            }
        }

        if ($parts) {
            /** @var Google_Service_Gmail_MessagePart $part */
            foreach ($parts as $part) {
                if ($part) {
                    if ($returnOnFirstFound) {
                        return true;
                    }

                    $this->allParts[$part->getPartId()] = $part;
                    $this->iterateParts(collect($part->getParts()));
                }
            }
        }
    }
}