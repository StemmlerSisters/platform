<?php

namespace Oro\Bundle\ImapBundle\Validator\Constraints;

use Oro\Bundle\ImapBundle\Validator\SmtpConnectionConfigurationValidator;
use Symfony\Component\Validator\Constraint;

/**
 * The constraint checks that SMTP connection can be established with provided configuration parameters
 */
class SmtpConnectionConfiguration extends Constraint
{
    public string $message = 'oro.imap.validator.configuration.connection.smtp';

    /**
     * {@inheritdoc}
     */
    public function validatedBy(): string
    {
        return SmtpConnectionConfigurationValidator::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
