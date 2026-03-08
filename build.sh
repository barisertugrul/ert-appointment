#!/usr/bin/env bash
# =============================================================================
#  build.sh — ERT Appointment (Lite) dağıtım ZIP oluşturucusu
#
#  Kullanım:
#    chmod +x build.sh
#    ./build.sh              # sürümü plugin header'dan okur
#    ./build.sh 1.2.0        # sürümü elle belirle
#    ./build.sh --skip-npm   # JS derlemesini atla (hızlı test)
#
#  Çıktı:
#    dist/ert-appointment-v<sürüm>.zip
#
#  Yapılanlar:
#    1. Plugin header'dan sürüm okunur.
#    2. npm ci + vite build (JS/CSS derleme).
#    3. composer install --no-dev (mevcutsa).
#    4. PO → MO derleme (msgfmt mevcutsa).
#    5. Geliştirme dosyaları dışarıda bırakılarak ZIP oluşturulur.
#    6. SHA-256 checksum ve içerik listesi yazdırılır.
# =============================================================================

set -euo pipefail

# ── Renkler ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

step()  { echo -e "${CYAN}  →${NC} $*"; }
ok()    { echo -e "${GREEN}  ✓${NC} $*"; }
warn()  { echo -e "${YELLOW}  ⚠${NC} $*"; }
die()   { echo -e "${RED}  ✗${NC} $*" >&2; exit 1; }
title() { echo -e "\n${BOLD}$*${NC}"; }

# ── Argüman ayrıştırma ────────────────────────────────────────────────────────
SKIP_NPM=false
VERSION_ARG=""

for arg in "$@"; do
    case "$arg" in
        --skip-npm) SKIP_NPM=true ;;
        --*)        warn "Bilinmeyen flag: $arg — yoksayılıyor" ;;
        *)          VERSION_ARG="$arg" ;;
    esac
done

# ── Sabitler ─────────────────────────────────────────────────────────────────
PLUGIN_SLUG="ert-appointment"
MAIN_FILE="${PLUGIN_SLUG}.php"
ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
TMP_DIR="$(mktemp -d)"

# Dağıtım ZIP'ine dahil EDİLMEYECEK dosya/klasörler
EXCLUDES=(
    ".git"
    ".gitignore"
    ".gitattributes"
    ".github"
    ".cursorrules"
    ".curssorrules"
    ".vscode"
    ".editorconfig"
    ".prettierignore"
    ".prettierrc"
    ".prettierignore"
    ".prettierrc"
    ".prettierignore"
    ".prettierrc"
    "node_modules"
    ".venv"
    "venv"
    "env"
    "tests"
    "*.sh"
    "phpunit.xml*"
    "phpcs.xml*"
    "phpstan.neon*"
    "*.lock"
    "*.log"
    "*.zip"
    "*.tar"
    "*.bak"
    ".DS_Store"
    "Thumbs.db"
    "*.orig"
    "vite.config.js"
    "package.json"
    "package-lock.json"
    ".env"
    ".env.*"
    "*.map"
    "scripts"
    "docs"
    "test-results"
    "WPORG_PRECHECK.md"
    "repomix-output.xml"
    "*.docx"
    "*.html"
)

# ── Sürüm ────────────────────────────────────────────────────────────────────
if [[ -n "$VERSION_ARG" ]]; then
    VERSION="$VERSION_ARG"
    step "Sürüm (argümandan): ${VERSION}"
else
    VERSION=$(grep -m1 '^ \* Version:' "${ROOT_DIR}/${MAIN_FILE}" \
        | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
    [[ -n "$VERSION" ]] || die "Sürüm okunamadı. './build.sh 1.0.0' şeklinde geçin."
    step "Sürüm (header'dan): ${VERSION}"
fi

ARCHIVE="${DIST_DIR}/${PLUGIN_SLUG}-v${VERSION}.zip"
STAGE="${TMP_DIR}/${PLUGIN_SLUG}"

# ── Başlık ───────────────────────────────────────────────────────────────────
title "══════════════════════════════════════════"
title "  ERT Appointment (Lite)  —  v${VERSION}"
title "══════════════════════════════════════════"
echo  "  Kaynak : ${ROOT_DIR}"
echo  "  Çıktı  : ${ARCHIVE}"
$SKIP_NPM && echo -e "  ${YELLOW}--skip-npm aktif${NC}"

# ── Ön kontroller ────────────────────────────────────────────────────────────
title "Ön kontroller"

[[ -f "${ROOT_DIR}/${MAIN_FILE}" ]] || die "Ana plugin dosyası bulunamadı: ${MAIN_FILE}"
ok "Ana dosya: ${MAIN_FILE}"

# zip veya 7z kontrolü
ZIP_CMD=""
if command -v zip &>/dev/null; then
    ZIP_CMD="zip"
    ok "zip mevcut"
elif command -v 7z &>/dev/null; then
    ZIP_CMD="7z"
    ok "7z (7-Zip) mevcut"
else
    die "Ne 'zip' ne de '7z' bulunamadı. Lütfen 7-Zip yükleyin: https://www.7-zip.org/"
fi

if ! $SKIP_NPM; then
    command -v node &>/dev/null || die "'node' bulunamadı. --skip-npm kullanabilirsiniz."
    command -v npm  &>/dev/null || die "'npm' bulunamadı."
    ok "Node.js: $(node -v)"
fi

# ── JS/CSS Derleme (Vite) ─────────────────────────────────────────────────────
title "JS/CSS Derleme"

if $SKIP_NPM; then
    warn "JS derleme atlandı (--skip-npm)"
    [[ -d "${ROOT_DIR}/assets/dist" ]] \
        && ok "Mevcut assets/dist/ kullanılacak" \
        || warn "assets/dist/ bulunamadı — önceden derlenmiş dosya yok!"
else
    step "npm ci..."
    (cd "${ROOT_DIR}" && npm ci --silent)
    ok "Bağımlılıklar yüklendi"

    step "vite build..."
    (cd "${ROOT_DIR}" && npm run build)
    ok "JS/CSS derlendi → assets/dist/"
fi

# ── Composer (--no-dev) ───────────────────────────────────────────────────────
title "Composer"

if command -v composer &>/dev/null && [[ -f "${ROOT_DIR}/composer.json" ]]; then
    step "composer install --no-dev --optimize-autoloader..."
    (cd "${ROOT_DIR}" && composer install --no-dev --optimize-autoloader --quiet)
    ok "Vendor yüklendi (dev paketler hariç)"
    # vendor/ dahil edilecek şekilde exclude listesinden çıkar
    EXCLUDES=("${EXCLUDES[@]/vendor/}")
else
    warn "composer bulunamadı veya composer.json yok — vendor/ atlanıyor"
fi

# ── PO → MO derleme ──────────────────────────────────────────────────────────
title "Çeviri derleme"

MSGFMT_BIN=""
if command -v msgfmt &>/dev/null; then
    MSGFMT_BIN="$(command -v msgfmt)"
elif command -v msgfmt.exe &>/dev/null; then
    MSGFMT_BIN="$(command -v msgfmt.exe)"
else
    # Windows / Git Bash yaygın konumlar
    CANDIDATES=(
        "/c/ProgramData/chocolatey/bin/msgfmt.exe"
        "/c/msys64/usr/bin/msgfmt.exe"
        "/c/msys64/mingw64/bin/msgfmt.exe"
        "/mingw64/bin/msgfmt.exe"
    )
    for cand in "${CANDIDATES[@]}"; do
        if [[ -x "$cand" ]]; then
            MSGFMT_BIN="$cand"
            break
        fi
    done
fi

if [[ -n "$MSGFMT_BIN" ]]; then
    step "msgfmt bulundu: ${MSGFMT_BIN}"
    compiled=0
    while IFS= read -r -d '' po; do
        mo="${po%.po}.mo"
        "$MSGFMT_BIN" "$po" -o "$mo"
        ok "$(basename "$po") → $(basename "$mo")"
        ((compiled++)) || true
    done < <(find "${ROOT_DIR}/languages" -name "*.po" -print0 2>/dev/null)
    [[ $compiled -eq 0 ]] && warn "languages/ içinde .po bulunamadı"
else
    warn "msgfmt bulunamadı — mevcut .mo dosyaları kullanılacak"
    warn "Windows için kurulum: 'choco install gettext' veya 'scoop install gettext'"
fi

# ── Dosyaları hazırla ────────────────────────────────────────────────────────
title "Dosyalar hazırlanıyor"

mkdir -p "$STAGE"

if command -v rsync &>/dev/null; then
    RSYNC_EX=()
    for ex in "${EXCLUDES[@]}"; do [[ -n "$ex" ]] && RSYNC_EX+=(--exclude="$ex"); done
    # Kök seviyedeki dist/ klasörünü hariç tut (önceki build çıktıları),
    # ancak assets/dist/ içindeki derlenmiş JS bundle'larını DAHİL ET.
    rsync -a --quiet --exclude="/dist" "${RSYNC_EX[@]}" "${ROOT_DIR}/" "${STAGE}/"
else
    cp -r "${ROOT_DIR}/." "${STAGE}/"
    for ex in "${EXCLUDES[@]}"; do
        [[ -z "$ex" ]] && continue
        find "${STAGE}" -name "$ex" -exec rm -rf {} + 2>/dev/null || true
    done
    # Kök seviyedeki dist klasörünü kaldır (varsa), assets/dist olduğu gibi kalsın.
    rm -rf "${STAGE}/dist"
fi

# Güvenlik ağı: dağıtımda asla bulunmaması gereken geliştirme/artık dosyaları kaldır.
rm -rf "${STAGE}/scripts" 2>/dev/null || true
rm -rf "${STAGE}/docs" 2>/dev/null || true
rm -rf "${STAGE}/test-results" 2>/dev/null || true
rm -f "${STAGE}/.cursorrules" 2>/dev/null || true
rm -f "${STAGE}/.curssorrules" 2>/dev/null || true
rm -f "${STAGE}/WPORG_PRECHECK.md" 2>/dev/null || true
rm -f "${STAGE}/repomix-output.xml" 2>/dev/null || true
rm -f "${STAGE}/phpcs.xml.dist" 2>/dev/null || true
find "${STAGE}" -maxdepth 1 -type f \( -name "*.docx" -o -name "*.html" \) -delete 2>/dev/null || true

[[ -f "${STAGE}/${MAIN_FILE}" ]] || die "Hazırlama başarısız — ${MAIN_FILE} stage'de yok"
ok "Dosyalar hazırlandı"

# ── ZIP oluştur ───────────────────────────────────────────────────────────────
title "ZIP oluşturuluyor"

mkdir -p "$DIST_DIR"
rm -f "$ARCHIVE"
if [[ "$ZIP_CMD" == "zip" ]]; then
    (cd "$TMP_DIR" && zip -r "$ARCHIVE" "${PLUGIN_SLUG}/" -x "*/.DS_Store" -x "*/__MACOSX/*" -q)
else
    # 7z kullan - Windows path düzeltmesi ile
    (cd "$TMP_DIR" && 7z a -tzip "$ARCHIVE" "${PLUGIN_SLUG}/" -x!"*/.DS_Store" -x!"*/__MACOSX/*" -bb0)
fi
ok "Oluşturuldu: $(basename "$ARCHIVE")"

# ── Doğrulama ────────────────────────────────────────────────────────────────
title "Doğrulama"

SIZE=$(du -sh "$ARCHIVE" | cut -f1)
step "Boyut: ${SIZE}"

if command -v sha256sum &>/dev/null; then
    SUM=$(sha256sum "$ARCHIVE" | awk '{print $1}')
elif command -v shasum &>/dev/null; then
    SUM=$(shasum -a 256 "$ARCHIVE" | awk '{print $1}')
fi
[[ -n "${SUM:-}" ]] && ok "SHA-256: ${SUM}"

step "İçerik (ilk 40 satır):"
if [[ "$ZIP_CMD" == "zip" ]]; then
    zip -sf "$ARCHIVE" | head -40
else
    7z l "$ARCHIVE" | tail -n +20 | head -40
fi

# ── Temizlik ─────────────────────────────────────────────────────────────────
rm -rf "$TMP_DIR"

echo ""
echo -e "${GREEN}${BOLD}  ✓ Build tamamlandı!${NC}"
echo    "  📦  ${ARCHIVE}"
echo ""
