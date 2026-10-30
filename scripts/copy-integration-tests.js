import fs from 'fs-extra';

const tests = 'tests/integration/Espo/Modules/CalendarFeed';
const fixtures = 'tests/integration/testData';

await fs.copy(tests, 'site/tests/integration/Espo/Modules/CalendarFeed', {overwrite: true});

if (await fs.pathExists(fixtures)) {
    await fs.copy(fixtures, 'site/tests/integration/testData', {overwrite: true});
}

const config = 'tests/integration/config.php';

if (await fs.pathExists(config)) {
    await fs.copy(config, 'site/tests/integration/config.php', {overwrite: true});
}
