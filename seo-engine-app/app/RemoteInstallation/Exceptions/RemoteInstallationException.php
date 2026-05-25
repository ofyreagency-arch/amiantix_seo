<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Exceptions;

use RuntimeException;

class RemoteInstallationException extends RuntimeException
{
    public static function connectivity(string $message = 'Connexion au serveur impossible.'): self
    {
        return new self($message);
    }

    public static function authentication(string $message = 'Les identifiants de connexion sont invalides.'): self
    {
        return new self($message);
    }

    public static function invalidPath(string $message = 'Le chemin du projet distant est invalide.'): self
    {
        return new self($message);
    }

    public static function unsupported(string $message): self
    {
        return new self($message);
    }

    public static function detection(string $message): self
    {
        return new self($message);
    }

    public static function execution(string $message): self
    {
        return new self($message);
    }
}
