<?php

declare(strict_types=1);

namespace ShlinkioApiTest\Shlink\Rest\Action;

use Cake\Chronos\Chronos;
use GuzzleHttp\RequestOptions;
use Shlinkio\Shlink\TestUtils\ApiTest\ApiTestCase;
use ShlinkioApiTest\Shlink\Rest\Utils\NotFoundUrlHelpersTrait;

use function sprintf;

class ResolveShortUrlActionTest extends ApiTestCase
{
    use NotFoundUrlHelpersTrait;

    /**
     * @test
     * @dataProvider provideDisabledMeta
     */
    public function shortUrlIsProperlyResolvedEvenWhenNotEnabled(array $disabledMeta): void
    {
        $shortCode = 'abc123';
        $url = sprintf('/short-urls/%s', $shortCode);
        $this->callShortUrl($shortCode);

        $editResp = $this->callApiWithKey(self::METHOD_PATCH, $url, [RequestOptions::JSON => $disabledMeta]);
        $visitResp = $this->callShortUrl($shortCode);
        $fetchResp = $this->callApiWithKey(self::METHOD_GET, $url);

        $this->assertEquals(self::STATUS_NO_CONTENT, $editResp->getStatusCode());
        $this->assertEquals(self::STATUS_NOT_FOUND, $visitResp->getStatusCode());
        $this->assertEquals(self::STATUS_OK, $fetchResp->getStatusCode());
    }

    public function provideDisabledMeta(): iterable
    {
        $now = Chronos::now();

        yield 'future validSince' => [['validSince' => $now->addMonth()->toAtomString()]];
        yield 'past validUntil' => [['validUntil' => $now->subMonth()->toAtomString()]];
        yield 'maxVisits reached' => [['maxVisits' => 1]];
    }

    /**
     * @test
     * @dataProvider provideInvalidUrls
     */
    public function tryingToResolveInvalidUrlReturnsNotFoundError(
        string $shortCode,
        ?string $domain,
        string $expectedDetail
    ): void {
        $resp = $this->callApiWithKey(self::METHOD_GET, $this->buildShortUrlPath($shortCode, $domain));
        $payload = $this->getJsonResponsePayload($resp);

        $this->assertEquals(self::STATUS_NOT_FOUND, $resp->getStatusCode());
        $this->assertEquals(self::STATUS_NOT_FOUND, $payload['status']);
        $this->assertEquals('INVALID_SHORTCODE', $payload['type']);
        $this->assertEquals($expectedDetail, $payload['detail']);
        $this->assertEquals('Short URL not found', $payload['title']);
        $this->assertEquals($shortCode, $payload['shortCode']);
        $this->assertEquals($domain, $payload['domain'] ?? null);
    }
}
