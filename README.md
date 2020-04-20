# CSV2JSON

CSV to JSON tool coding challenge.

Announcement: https://twitter.com/FredBouchery/status/1250483472042467336  
Challenge: https://gist.github.com/f2r/2f1e1fa27186ac670c21d8a0303aabf1

# How it works ?

A PHP 7.4 container is available, just run `make infra-up`.

# Workflow

Use make commands to run the various targets inside the container.  
To see available, target, just run `make`.

## Comfy VSCode config

If you use VSCode, you cann install the remote container plugin and use this configuration:

```json
{
  "dockerComposeFile": "../docker-compose.yaml",
  "workspaceFolder": "/workdir",
  "service": "php",
  "extensions": [
    "junstyle.php-cs-fixer",
    "felixfbecker.php-pack",
    "neilbrayfield.php-docblocker",
  ],
  "settings": {
    "terminal.integrated.shell.linux": "/bin/bash",
  }
}
```
