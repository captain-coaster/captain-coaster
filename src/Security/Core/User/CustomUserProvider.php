<?php

declare(strict_types=1);

namespace App\Security\Core\User;

use App\Entity\User;
use FOS\UserBundle\Model\UserManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider as BaseClass;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CustomUserProvider extends BaseClass
{
    private $validator;

    public function __construct(UserManagerInterface $userManager, array $properties, ValidatorInterface $validator)
    {
        parent::__construct($userManager, $properties);

        $this->validator = $validator;
    }

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

    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $service = $response->getResourceOwner()->getName();
        $setterId = sprintf('set%sId', ucfirst($service));
        $setterToken = sprintf('set%sAccessToken', ucfirst($service));
        // in symfony the method is getUsername but it's a facebook or google ID
        $identifier = $response->getUsername();

        /** @var User $user */
        $user = $this->userManager->findUserBy([$this->getProperty($response) => $identifier]);

        // no user found with identifier (google_id or facebook_id)
        if (null === $user) {
            // if user has email
            if (null !== $response->getEmail()) {
                // another try with email
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

        // don't override apiKey at every login
        if (null === $user->getApiKey()) {
            $user->setApiKey(Uuid::uuid4()->toString());
        }

        // don't override displayName at every login
        if (null === $user->getDisplayName()) {
            $user->setDisplayName($response->getNickname());
        }

        if (null === $response->getEmail()) {
            $user->setEmail(uniqid(15).'@notvalid.com');
        } else {
            $user->setEmail($response->getEmail());
        }

        $user->setPassword($identifier);
        $user->setProfilePicture($response->getProfilePicture());

        $errors = $this->validator->validate($user);

        if (\count($errors) > 0) {
            throw new CustomUserMessageAuthenticationException("Your $service username cannot be empty. Please update your $service profile, and try again.");
        }

        $this->userManager->updateUser($user);

        return $user;
    }
}
