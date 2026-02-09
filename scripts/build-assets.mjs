import fs from "node:fs/promises";
import path from "node:path";
import fg from "fast-glob";
import CleanCSS from "clean-css";
import { minify as terserMinify } from "terser";

const rootDir = process.cwd();
const cssSourceDir = path.join(rootDir, "Resources/Private/Assets/Css");
const jsSourceDir = path.join(rootDir, "Resources/Private/Assets/JavaScript");
const cssTargetDir = path.join(rootDir, "Resources/Public/Css");
const jsTargetDir = path.join(rootDir, "Resources/Public/JavaScript");

async function ensureDir(dirPath) {
  await fs.mkdir(dirPath, { recursive: true });
}

async function writeFileEnsured(filePath, content) {
  await ensureDir(path.dirname(filePath));
  await fs.writeFile(filePath, content, "utf8");
}

async function buildCss() {
  const cssFiles = await fg("**/*.css", { cwd: cssSourceDir, dot: false });
  const minifier = new CleanCSS({ level: 2 });

  for (const relativeFile of cssFiles) {
    const sourcePath = path.join(cssSourceDir, relativeFile);
    const targetPath = path.join(cssTargetDir, relativeFile);
    const cssContent = await fs.readFile(sourcePath, "utf8");
    const result = minifier.minify(cssContent);

    if (result.errors.length > 0) {
      throw new Error(`CSS minification failed for ${relativeFile}: ${result.errors.join("; ")}`);
    }

    await writeFileEnsured(targetPath, result.styles);
    console.log(`CSS built: ${relativeFile}`);
  }
}

async function buildJs() {
  const jsFiles = await fg("**/*.js", { cwd: jsSourceDir, dot: false });

  for (const relativeFile of jsFiles) {
    const sourcePath = path.join(jsSourceDir, relativeFile);
    const targetPath = path.join(jsTargetDir, relativeFile);
    const jsContent = await fs.readFile(sourcePath, "utf8");
    const result = await terserMinify(jsContent, {
      compress: true,
      mangle: true,
      format: { comments: false }
    });

    if (!result.code) {
      throw new Error(`JS minification failed for ${relativeFile}`);
    }

    await writeFileEnsured(targetPath, result.code);
    console.log(`JS built: ${relativeFile}`);
  }
}

async function main() {
  await ensureDir(cssTargetDir);
  await ensureDir(jsTargetDir);
  await buildCss();
  await buildJs();
  console.log("Asset build complete.");
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
