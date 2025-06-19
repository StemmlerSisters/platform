<?php

namespace Oro\Bundle\ImapBundle\Tests\Unit\Provider;

use HWI\Bundle\OAuthBundle\OAuth\ResourceOwnerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Http\ResourceOwnerMapInterface;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ImapBundle\Exception\OAuthAccessTokenFailureException;
use Oro\Bundle\ImapBundle\Exception\RefreshOAuthAccessTokenFailureException;
use Oro\Bundle\ImapBundle\Provider\GoogleOAuthProvider;
use Oro\Bundle\SecurityBundle\Encoder\SymmetricCrypterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class GoogleOAuthProviderTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private ResourceOwnerMapInterface&MockObject $resourceOwnerMap;
    private ConfigManager&MockObject $configManager;
    private GoogleOAuthProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->resourceOwnerMap = $this->createMock(ResourceOwnerMapInterface::class);
        $this->configManager = $this->createMock(ConfigManager::class);

        $crypter = $this->createMock(SymmetricCrypterInterface::class);
        $crypter->expects(self::any())
            ->method('decryptData')
            ->with(self::isType('string'))
            ->willReturnCallback(function ($data) {
                return $data . ' (decrypted)';
            });

        $this->provider = new GoogleOAuthProvider(
            $this->httpClient,
            $this->resourceOwnerMap,
            $this->configManager,
            $crypter
        );
    }

    private function expectGetConfig(array $values, bool $assertCallCount = true): void
    {
        $map = [];
        foreach ($values as $key => $val) {
            $map[] = [$key, false, false, null, $val];
        }
        $this->configManager->expects($assertCallCount ? self::exactly(count($map)) : self::any())
            ->method('get')
            ->willReturnMap($map);
    }

    private function expectSendRequest(array $parameters): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())
            ->method('getContent')
            ->willReturn(json_encode([
                'access_token'  => 'sampleAccessToken',
                'refresh_token' => 'sampleRefreshToken',
                'expires_in'    => 3600
            ], JSON_THROW_ON_ERROR));

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://www.googleapis.com/oauth2/v4/token',
                [
                    'headers' => [
                        'Content-length' => \strlen(http_build_query($parameters)),
                        'content-type'   => 'application/x-www-form-urlencoded',
                        'user-agent'     => 'oro-oauth'
                    ],
                    'body' => http_build_query($parameters)
                ]
            )
            ->willReturn($response);
    }

    public function testGetAuthorizationUrl(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'Not implemented. Use Google API Client Library for JavaScript to perform authorization requests.'
        );
        $this->provider->getAuthorizationUrl();
    }

    public function testGetRedirectUrl(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'Not implemented. Use Google API Client Library for JavaScript to perform authorization requests.'
        );
        $this->provider->getRedirectUrl();
    }

    public function testGetAccessTokenByAuthCode(): void
    {
        $this->expectGetConfig([
            'oro_google_integration.client_id'     => 'sampleClientId',
            'oro_google_integration.client_secret' => 'sampleClientSecret'
        ]);

        $this->expectSendRequest([
            'client_id'     => 'sampleClientId',
            'client_secret' => 'sampleClientSecret (decrypted)',
            'grant_type'    => 'authorization_code',
            'code'          => 'sampleCode',
            'redirect_uri'  => 'postmessage'
        ]);

        $tokenData = $this->provider->getAccessTokenByAuthCode('sampleCode');
        self::assertEquals('sampleAccessToken', $tokenData->getAccessToken());
        self::assertEquals('sampleRefreshToken', $tokenData->getRefreshToken());
        self::assertEquals(3600, $tokenData->getExpiresIn());
    }

    public function testGetAccessTokenByAuthCodeWithEmptyScopes(): void
    {
        $this->expectGetConfig([
            'oro_google_integration.client_id'     => 'sampleClientId',
            'oro_google_integration.client_secret' => 'sampleClientSecret'
        ]);

        $this->expectSendRequest([
            'client_id'     => 'sampleClientId',
            'client_secret' => 'sampleClientSecret (decrypted)',
            'grant_type'    => 'authorization_code',
            'code'          => 'sampleCode',
            'redirect_uri'  => 'postmessage'
        ]);

        $tokenData = $this->provider->getAccessTokenByAuthCode('sampleCode', []);
        self::assertEquals('sampleAccessToken', $tokenData->getAccessToken());
        self::assertEquals('sampleRefreshToken', $tokenData->getRefreshToken());
        self::assertEquals(3600, $tokenData->getExpiresIn());
    }

    public function testGetAccessTokenByAuthCodeWithCustomScopes(): void
    {
        $this->expectGetConfig([
            'oro_google_integration.client_id'     => 'sampleClientId',
            'oro_google_integration.client_secret' => 'sampleClientSecret'
        ]);

        $this->expectSendRequest([
            'client_id'     => 'sampleClientId',
            'client_secret' => 'sampleClientSecret (decrypted)',
            'grant_type'    => 'authorization_code',
            'code'          => 'sampleCode',
            'redirect_uri'  => 'postmessage'
        ]);

        $tokenData = $this->provider->getAccessTokenByAuthCode('sampleCode', ['scope1', 'scope2']);
        self::assertEquals('sampleAccessToken', $tokenData->getAccessToken());
        self::assertEquals('sampleRefreshToken', $tokenData->getRefreshToken());
        self::assertEquals(3600, $tokenData->getExpiresIn());
    }

    public function testGetAccessTokenByAuthCodeWhenFirstRequestFailed(): void
    {
        $this->expectGetConfig([
            'oro_google_integration.client_id'     => 'sampleClientId',
            'oro_google_integration.client_secret' => 'sampleClientSecret'
        ], false);

        $response1 = $this->createMock(ResponseInterface::class);
        $response1->expects(self::once())
            ->method('getContent')
            ->willReturn('');

        $response2 = $this->createMock(ResponseInterface::class);
        $response2->expects(self::once())
            ->method('getContent')
            ->willReturn(json_encode([
                'access_token'  => 'sampleAccessToken',
                'refresh_token' => 'sampleRefreshToken',
                'expires_in'    => 3600
            ], JSON_THROW_ON_ERROR));

        $this->httpClient->expects(self::exactly(2))
            ->method('request')
            ->with(
                'POST',
                'https://www.googleapis.com/oauth2/v4/token',
                self::isType('array'),
            )
            ->willReturnOnConsecutiveCalls($response1, $response2);

        $tokenData = $this->provider->getAccessTokenByAuthCode('sampleCode');
        self::assertEquals('sampleAccessToken', $tokenData->getAccessToken());
        self::assertEquals('sampleRefreshToken', $tokenData->getRefreshToken());
        self::assertEquals(3600, $tokenData->getExpiresIn());
    }

    public function testGetAccessTokenByAuthCodeWhenAllRequestsFailed(): void
    {
        $this->expectGetConfig([
            'oro_google_integration.client_id'     => 'sampleClientId',
            'oro_google_integration.client_secret' => 'sampleClientSecret'
        ], false);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::any())
            ->method('getContent')
            ->willReturn('');

        $this->httpClient->expects(self::exactly(4))
            ->method('request')
            ->with(
                'POST',
                'https://www.googleapis.com/oauth2/v4/token',
                self::isType('array'),
            )
            ->willReturn($response);

        $this->expectException(OAuthAccessTokenFailureException::class);
        $this->expectExceptionMessage('Cannot get OAuth access token. Authorization Code: sampleCode.');

        $this->provider->getAccessTokenByAuthCode('sampleCode');
    }

    public function testGetAccessTokenByRefreshToken(): void
    {
        $this->expectGetConfig([
            'oro_google_integration.client_id'     => 'sampleClientId',
            'oro_google_integration.client_secret' => 'sampleClientSecret'
        ]);

        $this->expectSendRequest([
            'client_id'     => 'sampleClientId',
            'client_secret' => 'sampleClientSecret (decrypted)',
            'grant_type'    => 'refresh_token',
            'refresh_token' => 'sampleSourceRefreshToken'
        ]);

        $tokenData = $this->provider->getAccessTokenByRefreshToken('sampleSourceRefreshToken');
        self::assertEquals('sampleAccessToken', $tokenData->getAccessToken());
        self::assertEquals('sampleRefreshToken', $tokenData->getRefreshToken());
        self::assertEquals(3600, $tokenData->getExpiresIn());
    }

    public function testGetAccessTokenByRefreshTokenWithEmptyScopes(): void
    {
        $this->expectGetConfig([
            'oro_google_integration.client_id'     => 'sampleClientId',
            'oro_google_integration.client_secret' => 'sampleClientSecret'
        ]);

        $this->expectSendRequest([
            'client_id'     => 'sampleClientId',
            'client_secret' => 'sampleClientSecret (decrypted)',
            'grant_type'    => 'refresh_token',
            'refresh_token' => 'sampleSourceRefreshToken'
        ]);

        $tokenData = $this->provider->getAccessTokenByRefreshToken('sampleSourceRefreshToken', []);
        self::assertEquals('sampleAccessToken', $tokenData->getAccessToken());
        self::assertEquals('sampleRefreshToken', $tokenData->getRefreshToken());
        self::assertEquals(3600, $tokenData->getExpiresIn());
    }

    public function testGetAccessTokenByRefreshTokenWithCustomScopes(): void
    {
        $this->expectGetConfig([
            'oro_google_integration.client_id'     => 'sampleClientId',
            'oro_google_integration.client_secret' => 'sampleClientSecret'
        ]);

        $this->expectSendRequest([
            'client_id'     => 'sampleClientId',
            'client_secret' => 'sampleClientSecret (decrypted)',
            'grant_type'    => 'refresh_token',
            'refresh_token' => 'sampleSourceRefreshToken',
            'scope'         => 'scope1 scope2'
        ]);

        $tokenData = $this->provider->getAccessTokenByRefreshToken('sampleSourceRefreshToken', ['scope1', 'scope2']);
        self::assertEquals('sampleAccessToken', $tokenData->getAccessToken());
        self::assertEquals('sampleRefreshToken', $tokenData->getRefreshToken());
        self::assertEquals(3600, $tokenData->getExpiresIn());
    }

    public function testGetAccessTokenByRefreshTokenWhenFirstRequestFailed(): void
    {
        $this->expectGetConfig([
            'oro_google_integration.client_id'     => 'sampleClientId',
            'oro_google_integration.client_secret' => 'sampleClientSecret'
        ], false);

        $response1 = $this->createMock(ResponseInterface::class);
        $response1->expects(self::once())
            ->method('getContent')
            ->willReturn('');

        $response2 = $this->createMock(ResponseInterface::class);
        $response2->expects(self::once())
            ->method('getContent')
            ->willReturn(json_encode([
                'access_token'  => 'sampleAccessToken',
                'refresh_token' => 'sampleRefreshToken',
                'expires_in'    => 3600
            ], JSON_THROW_ON_ERROR));

        $this->httpClient->expects(self::exactly(2))
            ->method('request')
            ->with(
                'POST',
                'https://www.googleapis.com/oauth2/v4/token',
                self::isType('array'),
            )
            ->willReturnOnConsecutiveCalls($response1, $response2);

        $tokenData = $this->provider->getAccessTokenByRefreshToken('sampleRefreshToken');
        self::assertEquals('sampleAccessToken', $tokenData->getAccessToken());
        self::assertEquals('sampleRefreshToken', $tokenData->getRefreshToken());
        self::assertEquals(3600, $tokenData->getExpiresIn());
    }

    public function testGetAccessTokenByRefreshTokenWhenAllRequestsFailed(): void
    {
        $this->expectGetConfig([
            'oro_google_integration.client_id'     => 'sampleClientId',
            'oro_google_integration.client_secret' => 'sampleClientSecret'
        ], false);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::any())
            ->method('getContent')
            ->willReturn('');

        $this->httpClient->expects(self::exactly(4))
            ->method('request')
            ->with(
                'POST',
                'https://www.googleapis.com/oauth2/v4/token',
                self::isType('array'),
            )
            ->willReturn($response);

        $this->expectException(RefreshOAuthAccessTokenFailureException::class);
        $this->expectExceptionMessage('Cannot refresh OAuth access token. Refresh Token: sampleRefreshToken.');

        $this->provider->getAccessTokenByRefreshToken('sampleRefreshToken');
    }

    public function testGetUserInfo(): void
    {
        $accessToken = 'sampleAccessToken';

        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $this->resourceOwnerMap->expects(self::once())
            ->method('getResourceOwnerByName')
            ->with('google')
            ->willReturn($resourceOwner);

        $response = $this->createMock(UserResponseInterface::class);
        $resourceOwner->expects(self::once())
            ->method('getUserInformation')
            ->with(['access_token' => $accessToken])
            ->willReturn($response);

        self::assertSame($response, $this->provider->getUserInfo($accessToken));
    }
}
