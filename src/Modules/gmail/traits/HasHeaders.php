<?php


namespace Syntax\LaravelMailIntegration\Modules\gmail\traits;


use Google_Service_Gmail_MessagePartHeader;
use Illuminate\Support\Collection;
use stdClass;

trait HasHeaders
{
    /**
     * Gets a single header from an existing email by name.
     *
     * @param $headerName
     *
     * @param string|null $regex  if this is set, value will be evaluated with the give regular expression.
     *
     * @return null|string
     */
    public function getHeader($headerName, string $regex = null): ?string
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
            return $value[1] ?? null;
        }

        return $value;
    }

    /**
     * Gets all the headers from an email and returns a collections
     *
     * @param $emailHeaders
     * @return Collection
     */
    private function buildHeaders($emailHeaders): Collection
    {
        $headers = [];

        foreach ($emailHeaders as $header) {
            /** @var Google_Service_Gmail_MessagePartHeader $header */

            $head = new stdClass();

            $head->key = $header->getName();
            $head->value = $header->getValue();

            $headers[] = $head;
        }

        return collect($headers);
    }

    public abstract function getHeaders();
}