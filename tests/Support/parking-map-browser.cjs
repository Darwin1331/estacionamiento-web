const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

// Called by the existing real-login/CSRF/MySQL audit while its transaction is open.
module.exports = async function checkParkingMap(context, base, spaceId, plate) {
    const page = await context.newPage();
    const errors = [];
    let requests = 0;
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (new URL(request.url()).pathname === '/espacios/mapa/datos') requests++; });
    const loaded = () => page.waitForFunction(() => document.querySelector('#map-groups')?.getAttribute('aria-busy') === 'false');
    const card = () => page.locator(`[data-space-id="${spaceId}"]`);
    const screenshotDirectory = path.join(os.tmpdir(), 'parking-final-review');
    fs.mkdirSync(screenshotDirectory, {recursive: true});
    try {
        await page.clock.install();
        await page.goto(base+'/espacios');
        await page.getByRole('link', {name: 'Mapa de ocupación', exact: true}).click();
        await loaded();
        assert.equal(requests, 1);
        assert(await card().evaluate(el => el.classList.contains('parking-space-occupied')));
        assert((await card().innerText()).includes(plate));
        assert.equal(await page.locator('#nav-gestion.show a[aria-current="page"]').innerText(), 'Espacios');
        await page.screenshot({path: path.join(screenshotDirectory, 'mapa-escritorio.png'), fullPage: true});
        await page.setViewportSize({width: 375, height: 812});
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Map overflows on mobile');
        await page.screenshot({path: path.join(screenshotDirectory, 'mapa-movil.png'), fullPage: true});
        await page.setViewportSize({width: 1360, height: 900});

        // Clock advancement executes the real timer while requests still hit Laravel/MySQL.
        let next = page.waitForResponse(response => new URL(response.url()).pathname === '/espacios/mapa/datos');
        await page.clock.fastForward(30000);
        await next; await loaded();
        assert.equal(requests, 2, 'Periodic refresh did not run exactly once');

        // Drive the visibility event deterministically; the endpoint is not mocked here.
        await page.evaluate(() => {
            Object.defineProperty(document, 'hidden', {configurable: true, get: () => true});
            document.dispatchEvent(new Event('visibilitychange'));
        });
        await page.clock.fastForward(90000);
        assert.equal(requests, 2, 'Hidden page continued polling');
        next = page.waitForResponse(response => new URL(response.url()).pathname === '/espacios/mapa/datos');
        await page.evaluate(() => {
            Object.defineProperty(document, 'hidden', {configurable: true, get: () => false});
            document.dispatchEvent(new Event('visibilitychange'));
        });
        await next; await loaded();
        assert.equal(requests, 3, 'Visible page did not refresh');

        let release, intercepted;
        const gate = new Promise(resolve => {release = resolve;});
        const started = new Promise(resolve => {intercepted = resolve;});
        await page.route('**/espacios/mapa/datos*', async route => {intercepted(); await gate; await route.continue();}, {times: 1});
        await page.locator('#map-refresh').click(); await started;
        assert(await page.locator('#map-refresh').isDisabled());
        await page.evaluate(() => { for (let i = 0; i < 5; i++) window.jQuery('#map-refresh').triggerHandler('click'); });
        assert.equal(requests, 4, 'Duplicate refresh escaped the in-flight guard');
        next = page.waitForResponse(response => new URL(response.url()).pathname === '/espacios/mapa/datos');
        release(); await next; await loaded();

        // Network failure must preserve the last successful view and announce staleness.
        await page.route('**/espacios/mapa/datos*', route => route.abort('failed'), {times: 1});
        await page.locator('#map-refresh').click();
        await page.locator('#map-error:not(.d-none)').waitFor(); await loaded();
        assert((await card().innerText()).includes(plate));
        next = page.waitForResponse(response => new URL(response.url()).pathname === '/espacios/mapa/datos');
        await page.locator('#map-refresh').click(); await next; await loaded();
        assert(await page.locator('#map-error').evaluate(el => el.classList.contains('d-none')));

        // Untrusted data must render as text. Only this display test uses a synthetic response.
        const snapshot = await page.evaluate(url => window.jQuery.getJSON(url), base+'/espacios/mapa/datos');
        const special = snapshot.data.find(space => space.id === spaceId);
        special.code = '<img src=x onerror="window.mapXss=true">';
        special.plates = ['<script>window.mapXss=true</script>'];
        await page.route('**/espacios/mapa/datos*', route => route.fulfill({json: snapshot}), {times: 1});
        await page.locator('#map-refresh').click(); await loaded();
        assert.equal(await card().locator('img, script').count(), 0);
        assert.equal(await page.evaluate(() => window.mapXss), undefined);

        // Simulate a revoked-session response; authorization itself is tested against Laravel.
        await page.route('**/espacios/mapa/datos*', route => route.fulfill({status: 403, json: {message: 'Forbidden'}}), {times: 1});
        await page.locator('#map-refresh').click();
        await page.locator('#map-error:not(.d-none)').waitFor(); await loaded();
        assert.equal(await page.locator('[data-space-id]').count(), 0);
        assert(await page.locator('#map-refresh').isDisabled());
        const stoppedCount = requests;
        await page.clock.fastForward(90000);
        assert.equal(requests, stoppedCount);
        assert.deepEqual(errors, []);
        console.log('Edge + MySQL: mapa ocupado, agrupación, móvil, actualización manual/periódica, pausa, exclusión de duplicados, recuperación y escape HTML verificados.');
        console.log('Capturas reales guardadas en: '+screenshotDirectory);
    } finally {
        await page.close();
    }
};
