<?php

namespace BddBundle\Security\Core\User;

use BddBundle\Entity\User;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider as BaseClass;
use Symfony\Component\Security\Core\User\UserInterface;

class CustomUserProvider extends BaseClass
{
    /**
     * {@inheritDoc}
     */
    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        $property = $this->getProperty($response);
        $username = $response->getUsername();
        //on connect - get the access token and the user ID
        $service = $response->getResourceOwner()->getName();
        $setter = 'set'.ucfirst($service);
        $setter_id = $setter.'Id';
        $setter_token = $setter.'AccessToken';
        //we "disconnect" previously connected users
        if (null !== $previousUser = $this->userManager->findUserBy([$property => $username])) {
            $previousUser->$setter_id(null);
            $previousUser->$setter_token(null);
            $this->userManager->updateUser($previousUser);
        }
        //we connect current user
        $user->$setter_id($username);
        $user->$setter_token($response->getAccessToken());
        $this->userManager->updateUser($user);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $username = $response->getUsername();

        /** @var User $user */
        $user = $this->userManager->findUserBy([$this->getProperty($response) => $username]);

        $service = $response->getResourceOwner()->getName();
        $setter = 'set'.ucfirst($service);
        $setterId = $setter.'Id';
        $setterToken = $setter.'AccessToken';

        // Auto register user
        if (null === $user) {
            $user = $this->userManager->createUser();
            $user->$setterId($response->getUsername());
        }

        $user->$setterToken($response->getAccessToken());

        $user->setFirstName($response->getFirstName());
        $user->setLastName($response->getLastName());
        $user->setUsername($response->getNickname());
        $user->setDisplayName($response->getNickname());

        if (is_null($response->getEmail())) {
            $user->setEmail(uniqid(15).'@notvalid.com');
        } else {
            $user->setEmail($response->getEmail());
        }

        $user->setPassword($response->getUsername());
        $user->setProfilePicture($response->getProfilePicture());
        $user->setEnabled(true);
        $this->userManager->updateUser($user);

        return $user;
    }
}