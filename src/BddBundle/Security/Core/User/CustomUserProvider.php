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

        // on connect - get the access token and the user ID
        $service = $response->getResourceOwner()->getName();
        $setterId = sprintf('set%sId', ucfirst($service));
        $setterToken = sprintf('set%sAccessToken', ucfirst($service));

        // we "disconnect" previously connected users
        if (null !== $previousUser = $this->userManager->findUserBy([$property => $username])) {
            $previousUser->$setterId(null);
            $previousUser->$setterToken(null);
            $this->userManager->updateUser($previousUser);
        }

        // we connect current user
        $user->$setterId($username);
        $user->$setterToken($response->getAccessToken());

        $this->userManager->updateUser($user);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $service = $response->getResourceOwner()->getName();
        $setterId = sprintf('set%sId', ucfirst($service));
        $setterToken = sprintf('set%sAccessToken', ucfirst($service));
        // in symfony the method is getUsername but it's a facebook or google ID
        $identifier = $response->getUsername();

        /** @var User $user */
        $user = $this->userManager->findUserBy([$this->getProperty($response) => $identifier]);

        // no user found with identifier
        if (null === $user) {
            // if user has email
            if (null !== $response->getEmail()) {
                // another try with with email
                $user = $this->userManager->findUserBy(['email' => $response->getEmail()]);
            }

            // if not found again, create a new user
            if (null === $user) {
                $user = $this->userManager->createUser();
                // enable only new users
                $user->setEnabled(true);
            }

            $user->$setterId($identifier);
        }

        $user->$setterToken($response->getAccessToken());

        // set or reset name
        $user->setFirstName($response->getFirstName());
        $user->setLastName($response->getLastName());
        $user->setUsername($response->getNickname());
        $user->setDisplayName($response->getNickname());

        if (is_null($response->getEmail())) {
            $user->setEmail(uniqid(15).'@notvalid.com');
        } else {
            $user->setEmail($response->getEmail());
        }

        $user->setPassword($identifier);
        $user->setProfilePicture($response->getProfilePicture());

        $this->userManager->updateUser($user);

        return $user;
    }
}
