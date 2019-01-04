<?php

namespace App\Validator\Constraints;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ReCaptchaValidator extends ConstraintValidator
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $hostname;

    /**
     * @var string
     */
    private $secret;

    public function __construct(RequestStack $rs, string $hostname, string $secret)
    {
        $this->requestStack = $rs;
        $this->hostname = $hostname;
        $this->secret = $secret;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ReCaptcha) {
            throw new UnexpectedTypeException($constraint, ReCaptcha::class);
        }

        $request = $this->requestStack->getCurrentRequest();

        $recaptcha = new \ReCaptcha\ReCaptcha($this->secret);
        $response = $recaptcha
            ->setExpectedHostname($this->hostname)
            ->verify(
                $request->get('g-recaptcha-response'),
                $request->getClientIp()
            );

        if (!$response->isSuccess()) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }

        return;
    }
}
