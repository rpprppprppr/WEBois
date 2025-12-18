<?php

namespace Legacy\API;

use CUser;
use Legacy\General\Constants;

class Auth
{
    private static function mapUser(array $arUser): array
    {
        $userGroups = CUser::GetUserGroup($arUser['ID']);

        return [
            'id'            => $arUser['ID'],
            'login'         => $arUser['LOGIN'],
            'email'         => $arUser['EMAIL'],
            'firstName'     => $arUser['NAME'] ?? '',
            'lastName'      => $arUser['LAST_NAME'] ?? '',
            'secondName'    => $arUser['SECOND_NAME'] ?? '',
            'isTeacher'     => in_array(Constants::GROUP_TEACHERS, $userGroups),
            'isStudent'     => in_array(Constants::GROUP_STUDENTS, $userGroups),
            'isAdmin'       => in_array(Constants::GROUP_ADMINS, $userGroups)
        ];
    }

    // Авторизация
    // /api/Auth/login/
    public static function login(array $arRequest): array
    {
        global $USER;

        $login    = trim($arRequest['login'] ?? '');
        $password = (string)($arRequest['password'] ?? '');

        if ($login === '' || $password === '') {
            throw new \Exception('Login and password are required');
        }

        if ($USER->Login($login, $password, 'Y') !== true) {
            throw new \Exception('Invalid login or password');
        }

        $arUser = CUser::GetByID($USER->GetID())->Fetch();

        return [
            'user' => self::mapUser($arUser),
        ];
    }

    // Выход
    // /api/Auth/logout/
    public static function logout(): array
    {
        global $USER;
        $USER->Logout();

        return [
            'success' => true,
        ];
    }

    // Получение профиля текущего пользователя
    // /api/Auth/profile/
    public static function profile(): array
    {
        global $USER;

        if (!$USER->IsAuthorized()) {
            throw new \Exception('User not authorized');
        }

        $rsUser = CUser::GetByID($USER->GetID());
        $arUser = $rsUser->Fetch();

        return self::mapUser($arUser);
    }
}