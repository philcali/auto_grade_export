# Auto Export Plugin

Please go to the [wiki][1] for more information about how to install, configure,
and use the plugin.

[1]: https://github.com/philcali/up_auto_export/wiki

## Building the documentation

A Moodle block that handles grade exporting to a configurable external Oracle
database.

## Requirements

- `cs philcali/lmxml`
- `npm install -g less`
- `cs philcali/monido`
- `cs softprops/unplanned`

## Usage

Screen `#1`:

```
monido src/ -e sh build.sh
```

Screen `#2`:

```
cd out; up -p 8080
```

Screen `#3`:

Continuing editing the `index.lmxml` file.
