<?php

namespace Reaction\Web;

/**
 * ResponseFormatterInterface specifies the interface needed to format a response before it is sent out.
 */
interface ResponseFormatterInterface
{
    /**
     * Formats the specified response.
     * @param ResponseBuilderInterface $responseBuilder the response to be formatted.
     * @return array|string|\Psr\Http\Message\StreamInterface
     */
    public function format($responseBuilder);
}
