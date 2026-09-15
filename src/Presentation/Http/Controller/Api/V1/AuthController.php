<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Controller\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shortwave\Application\Account\Command\AuthenticateAccount;
use Shortwave\Application\Account\Command\RegisterAccount;
use Shortwave\Application\Account\DTO\AccountView;
use Shortwave\Application\Account\Handler\AuthenticateAccountHandler;
use Shortwave\Application\Account\Handler\RegisterAccountHandler;
use Shortwave\Application\Account\Port\TokenIssuer;
use Shortwave\Domain\Account\Repository\AccountRepository;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Presentation\Http\Controller\Controller;
use Shortwave\Presentation\Http\Request\V1\LoginRequest;
use Shortwave\Presentation\Http\Request\V1\RegisterRequest;
use Shortwave\Presentation\Http\Resource\V1\AccountResource;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterAccountHandler $handler): JsonResponse
    {
        /** @var array{email: string, name: string, password: string, token_name?: string} $input */
        $input = $request->validated();

        $result = $handler->handle(new RegisterAccount(
            email: $input['email'],
            name: $input['name'],
            password: $input['password'],
            tokenName: $input['token_name'] ?? 'default',
        ));

        return new JsonResponse(
            AccountResource::withToken($result['account'], $result['token']),
            201,
        );
    }

    public function login(LoginRequest $request, AuthenticateAccountHandler $handler): JsonResponse
    {
        /** @var array{email: string, password: string, token_name?: string} $input */
        $input = $request->validated();

        $result = $handler->handle(new AuthenticateAccount(
            email: $input['email'],
            password: $input['password'],
            tokenName: $input['token_name'] ?? 'default',
        ));

        return new JsonResponse(AccountResource::withToken($result['account'], $result['token']));
    }

    public function me(Request $request, AccountRepository $accounts): JsonResponse
    {
        $account = $accounts->findById(AccountId::fromString($this->accountId($request)));

        if ($account === null) {
            // The token authenticated but the account is gone — a deletion that
            // raced this request. 401 is the honest answer.
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        return new JsonResponse(['account' => AccountResource::one(AccountView::fromEntity($account))]);
    }

    /**
     * Revokes the token that made this request, leaving the account's other tokens
     * alone — logging out of one client should not sign out the others.
     */
    public function logout(Request $request, TokenIssuer $tokens): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        $tokenId = $token?->getAttribute('id');

        if (is_scalar($tokenId)) {
            $tokens->revokeCurrent(
                AccountId::fromString($this->accountId($request)),
                (string) $tokenId,
            );
        }

        return new JsonResponse(status: 204);
    }
}
