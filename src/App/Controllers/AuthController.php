<?php

namespace App\Controllers;

use App\ApiResponse;
use App\Controller;
use App\Mailable\JoinConfirmMailable;
use DateTime;
use Exception;
use Firebase\JWT\JWT;
use Map\UserTableMap;
use Propel\Runtime\Propel;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;
use User;
use UserQuery;
use Util;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * @param Request $request
     * @return Response
     * @throws \Propel\Runtime\Exception\PropelException
     * @throws Exception
     */
    public function store(Request $request)
    {
        $validation = $this->validator->validate($request, [
            'email' => v::notEmpty()->noWhitespace()->email(),
            'name' => v::notEmpty()->stringType()->length(4, 20)->alnum(),
            'password' => v::notEmpty()->length(4, null),
            'password_confirm' => v::equals($request->getParam('password')),
        ]);

        if ($validation->failed()) {
            return $this->failToJson($validation->getErrors());
        }

        $count = UserQuery::create()
            ->filterByEmail($request->getParam('email'))
            ->count();

        if ($count > 0) {
            return $this->failToJson('해당 이메일은 이미 존재합니다.');
        }

        $con = Propel::getWriteConnection(UserTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $confirmCode = Util::random(60);
            $user = new User();
            $user->setEmail($request->getParam('email'));
            $user->setName($request->getParam('name'));
            $user->setPassword(password_hash($request->getParam('password'), PASSWORD_BCRYPT));
            $user->setConfirmCode($confirmCode);
            $user->save($con);
            $user->reload();

            $this->mailer->setTo($user->getEmail(), $user->getName())->sendMessage(new JoinConfirmMailable($user));

            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }

        return $this->successToJson(
            $user->toArray()
        );
    }

    public function login(Request $request, Response $response)
    {
        $validation = $this->validator->validate($request, [
            'email' => v::notEmpty(),
            'password' => v::notEmpty(),
        ]);

        if ($validation->failed()) {
            return $this->failToJson($validation->getErrors());
        }

        $user = UserQuery::create()
            ->findOneByEmail($request->getParam('email'));

        if (is_null($user) || !password_verify($request->getParam('password'), $user->getPassword())) {
            return $this->failToJson('아이디 또는 패스워드가 일치하지 않습니다.');
        }

        if (!$user->getActivated()) {
            return $this->failToJson('아직 승인받지 않은 상태입니다. 이메일을 확인해주세요.');
        }

        $token = $this->tokenEncode($user->getId());

        return $response->withHeader("Content-Type", "application/json")
            ->withJson($token, 200);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function confirm(Request $request, Response $response)
    {
        $user = UserQuery::create()
            ->findOneByConfirmCode($request->getParam('confirmCode'));

        if (is_null($user)) {
            return $this->failToJson('승인코드가 존재하지 않거나 일치하지 않습니다.');
        }

        if ($user->getActivated()) {
            return $this->failToJson('이미 승인되었습니다.');
        }

        $user->setActivated(true);
        $user->setConfirmCode(null);
        $user->save();

        return $this->successToJson(
            $user->toArray()
        );
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws Exception
     */
    public function tokenRefresh(Request $request, Response $response)
    {
        if (is_null($request->getParam('token'))) {
            return $this->failToJson('갱신하기 위한 토큰이 존재하지 않습니다.');
        }

        $decoded = $this->tokenDecode($request->getParam('token'));
        $token = $this->tokenEncode($decoded['id']);

        return $response->withHeader("Content-Type", "application/json")
            ->withJson($token, 200);
    }

    /**
     * @param $token
     * @return array
     * @throws Exception
     */
    private function tokenDecode($token)
    {
        try {
            $decoded = JWT::decode(
                $token,
                $this->settings['jwt']['secret'],
                (array) $this->settings['jwt']['algorithm']
            );
            return (array) $decoded;
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function tokenEncode($userId)
    {
        $now = new DateTime();
        $future = new DateTime("now +{$this->settings['jwt']['ttl']} hours");
        $jti = Util::random(12);

        $payload = [
            "iat" => $now->getTimeStamp(),
            "exp" => $future->getTimeStamp(),
            "jti" => $jti,
            "id" => $userId,
        ];

        $token = [
            'token' => JWT::encode($payload, $this->settings['jwt']['secret'], $this->settings['jwt']['algorithm'])
        ];

        return $token;
    }

    public function logout()
    {
        $this->token->unsetToken();
    }
}