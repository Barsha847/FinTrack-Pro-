<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\DatabaseTestCase;
use App\Services\AuthService;
use App\Repositories\UserRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Throwable;

class ApiAuthTest extends DatabaseTestCase
{
    private AuthService $authService;
    private UserRepository $userRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthService();
        $this->userRepo = new UserRepository();
    }

    /**
     * Test that an unauthenticated token cannot be decoded or verified.
     */
    public function test_unauthenticated_token_validation_fails(): void
    {
        $invalidToken = 'invalid.bearer.token';
        $secrets = $this->authService->getJwtSecrets();
        
        $decoded = null;
        $failed = false;
        try {
            $decoded = JWT::decode($invalidToken, new Key($secrets[0], 'HS256'));
        } catch (Throwable $e) {
            $failed = true;
        }

        $this->assertTrue($failed, "Decoding an invalid token must fail.");
        $this->assertNull($decoded, "Decoded token must be null.");
    }

    /**
     * Test that expired tokens are strictly rejected.
     */
    public function test_expired_token_is_rejected(): void
    {
        $secrets = $this->authService->getJwtSecrets();
        $expiredPayload = [
            'iss' => 'FinTrack Pro',
            'aud' => 'FinTrack Pro Client',
            'iat' => time() - 3600,
            'exp' => time() - 1800, // Expired 30 mins ago
            'sub' => 'dummy-uuid',
            'role' => 'user',
            'email' => 'test@example.com'
        ];
        
        $expiredToken = JWT::encode($expiredPayload, $secrets[0], 'HS256');

        $this->expectException(ExpiredException::class);
        JWT::decode($expiredToken, new Key($secrets[0], 'HS256'));
    }

    /**
     * Test that seeded CI test users can generate and verify valid JWT tokens.
     */
    public function test_authenticated_test_user_tokens_generation_and_validation(): void
    {
        $user = $this->userRepo->findByEmail('test@example.com');
        if (!$user) {
            $this->markTestSkipped("Test User A not found in database. Run database seeder first.");
        }

        $tokens = $this->authService->generateTokens($user);

        $this->assertNotEmpty($tokens['accessToken'], "Access token must not be empty.");
        $this->assertNotEmpty($tokens['refreshToken'], "Refresh token must not be empty.");

        // Decode and verify claims
        $secrets = $this->authService->getJwtSecrets();
        $decoded = JWT::decode($tokens['accessToken'], new Key($secrets[0], 'HS256'));

        $this->assertEquals($user['id'], $decoded->sub);
        $this->assertEquals($user['email'], $decoded->email);
        $this->assertEquals($user['role'] ?? 'user', $decoded->role);
    }

    /**
     * Test credential verification rejects invalid passwords and accepts valid passwords.
     */
    public function test_credential_verification(): void
    {
        $user = $this->userRepo->findByEmail('test@example.com');
        if (!$user) {
            $this->markTestSkipped("Test User A not found in database. Run database seeder first.");
        }

        // Correct password matches hash
        $valid = password_verify('TestPassword123!', $user['password_hash']);
        $this->assertTrue($valid, "Valid test password must verify successfully.");

        // Incorrect password fails verification
        $invalid = password_verify('IncorrectPassword999!', $user['password_hash']);
        $this->assertFalse($invalid, "Invalid password must not verify.");
    }

    /**
     * Test JWT Key Rotation support.
     */
    public function test_jwt_key_rotation_resolution(): void
    {
        $secrets = $this->authService->getJwtSecrets();
        $this->assertIsArray($secrets);
        $this->assertNotEmpty($secrets, "JWT secrets list must not be empty.");

        foreach ($secrets as $secret) {
            $this->assertGreaterThanOrEqual(32, strlen($secret), "JWT secret must be at least 32 characters long.");
        }
    }
}
