<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail\traits;



use Illuminate\Support\Collection;

trait HasParts
{

    /**
     * Gets all the headers from an email and returns a collections
     *
     * @param $emailHeaders
     * @return Collection
     */
    private function buildHeaders($emailHeaders)
    {
        $headers = [];

        foreach ($emailHeaders as $header) {
            /** @var \Google_Service_Gmail_MessagePartHeader $header */

            $head = new \stdClass();

            $head->key = $header->getName();
            $head->value = $header->getValue();

            $headers[] = $head;
        }

        return collect($headers);
    }

    /**
     * Gets a single header from an existing email by name.
     *
     * @param $headerName
     *
     * @param  string  $regex  if this is set, value will be evaluated with the give regular expression.
     *
     * @return null|string
     */
    public function getHeader($headerName, $regex = null)
    {
        $headers = $this->getHeaders();

        $value = null;

        foreach ($headers as $header) {
            if ($header->key === $headerName) {
                $value = $header->value;
                if (!is_null($regex)) {
                    preg_match_all($regex, $header->value, $value);
                }
                break;
            }
        }

        if (is_array($value)) {
            return isset($value[1]) ? $value[1] : null;
        }

        return $value;
    }

    public abstract function getHeaders();

    /**
     * LOL
     * @var Collection
     */
    private $allParts;

    /**
     * Find all Parts of a message.
     * Necessary to reset the $allParts Varibale.
     *
     * @param  collection  $partsContainer  . F.e. collect([$message->payload])
     *
     * @return Collection of all 'parts' flattened
     */
    private function getAllParts($partsContainer)
    {
        $this->iterateParts($partsContainer);

        return collect($this->allParts);
    }


    /**
     * Recursive Method. Iterates through a collection,
     * finding all 'parts'.
     *
     * @param  collection  $partsContainer
     * @param  bool  $returnOnFirstFound
     *
     * @return Collection|boolean
     */

    private function iterateParts($partsContainer, $returnOnFirstFound = false)
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