#!/usr/bin/env bash
# One-time (or per-region-add) setup for the OSRM road-snapping service:
# downloads an OpenStreetMap extract and preprocesses it into the routing
# graph files `osrm-routed` serves. Re-run whenever REGION_URL changes or the
# map data needs refreshing — it's idempotent (skips the download/extract
# steps if their output already exists) but always re-runs partition +
# customize since those are cheap relative to extract.
#
# Usage: run from the same directory as docker-compose.yml —
#   ./scripts/setup-osrm.sh [.osm.pbf-url]
# (in production that's /opt/carenexa360, where this script gets copied
# standalone rather than checked out as part of the repo).
# Defaults to Zimbabwe — the region carer tracking actually operates in
# today. Add another region by running this again with its URL; each
# extract's .osrm files live side by side under osrm-data/, and
# OSRM_REGION_FILE (docker-compose.yml / .env) picks which one is served.

set -euo pipefail

REGION_URL="${1:-https://download.openstreetmap.fr/extracts/africa/zimbabwe-latest.osm.pbf}"
# Deliberately based on the current directory, not this script's own location
# ($BASH_SOURCE) — docker-compose.yml's `./osrm-data` mount is relative to
# wherever `docker compose` is run from, so this has to land in the same
# place. Run this from the directory holding docker-compose.yml, same as
# any other compose command.
DATA_DIR="$(pwd)/osrm-data"
PBF_NAME="$(basename "$REGION_URL")"
BASE_NAME="${PBF_NAME%.osm.pbf}"

mkdir -p "$DATA_DIR"

if [ ! -f "$DATA_DIR/$PBF_NAME" ]; then
  echo "==> Downloading $REGION_URL"
  curl -L --fail -A "Mozilla/5.0" -o "$DATA_DIR/$PBF_NAME.tmp" "$REGION_URL"
  mv "$DATA_DIR/$PBF_NAME.tmp" "$DATA_DIR/$PBF_NAME"
else
  echo "==> $PBF_NAME already downloaded, skipping"
fi

# osrm-extract consumes the .pbf and needs the car profile bundled in the
# image; osrm-partition/customize turn that into the MLD graph osrm-routed
# actually loads. All three run as one-off containers against the same
# bind-mounted data dir, uid-mapped to the caller so the output files aren't
# left root-owned on the host.
run_osrm() {
  # MSYS_NO_PATHCONV: Git Bash on Windows otherwise rewrites the container-side
  # absolute paths below (/opt/car.lua, /data/...) into host Windows paths
  # before docker ever sees them.
  MSYS_NO_PATHCONV=1 docker run --rm -t \
    -u "$(id -u):$(id -g)" \
    -v "$DATA_DIR:/data" \
    ghcr.io/project-osrm/osrm-backend \
    "$@"
}

if [ ! -f "$DATA_DIR/$BASE_NAME.osrm" ]; then
  echo "==> osrm-extract (car profile)"
  run_osrm osrm-extract -p /opt/car.lua "/data/$PBF_NAME"
else
  echo "==> $BASE_NAME.osrm already extracted, skipping"
fi

echo "==> osrm-partition"
run_osrm osrm-partition "/data/$BASE_NAME.osrm"

echo "==> osrm-customize"
run_osrm osrm-customize "/data/$BASE_NAME.osrm"

echo "==> Done. Set OSRM_REGION_FILE=$BASE_NAME.osrm and run: docker compose up -d osrm"
