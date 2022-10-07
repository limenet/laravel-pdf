/*eslint-env node */
const puppeteer = require("puppeteer");
const fs = require("fs-extra");

const args = process.argv.slice(2);

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
        await page.goto(args[0], { waitUntil: "networkidle2" });

        await page.pdf({
            path: args[3],
            format: args[4],
            margin: {
                top: args[6],
                right: args[7],
                bottom: args[8],
                left: args[9],
            },
            landscape: args[5] === "yes",
            headerTemplate: fs.readFileSync(args[1], "utf-8"),
            footerTemplate: fs.readFileSync(args[2], "utf-8"),
            displayHeaderFooter: true,
        });

        await browser.close();
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
