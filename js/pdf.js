/*eslint-env node */
const puppeteer = require("puppeteer");
const fs = require("fs-extra");

const args = process.argv.slice(2);
const url = args[0];
const headerHtmlFile = args[1];
const footerHtmlFile = args[2];
const pdfFile = args[3];

process.on("unhandledRejection", (result, error) => {
    console.log(result);
    console.log(error);
    process.exit(1);
});

let exitCode = 0;
(async () => {
    let browser = null;
    try {
        browser = await puppeteer.launch({
            args: ["--no-sandbox"],
            ignoreHTTPSErrors: true,
            acceptInsecureCerts: true,
        });
        const page = await browser.newPage();
        await page.goto(url, { waitUntil: "networkidle2" });

        await page.pdf({
            path: pdfFile,
            format: "A4",
            margin: {
                top: "1 cm",
                right: "1.5 cm",
                bottom: "2.5 cm",
                left: "1.5 cm",
            },
            headerTemplate: fs.readFileSync(headerHtmlFile, "utf-8"),
            footerTemplate: fs.readFileSync(footerHtmlFile, "utf-8"),
            displayHeaderFooter: true,
        });

        await browser.close();
        process.stdout.write(`${pdfFile}`);
    } catch (e) {
        console.log(e);
        exitCode = 1;
    } finally {
        if (browser) {
            await browser.close();
        }
        process.exit(exitCode);
    }
})();
