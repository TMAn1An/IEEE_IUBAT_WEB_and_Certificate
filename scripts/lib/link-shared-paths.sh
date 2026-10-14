#!/bin/bash
# Reference implementation + test subject for the defensive .env/storage
# symlinking logic embedded in .github/workflows/deploy-production.yml's
# "building new release" step (that step runs over SSH on the production
# server, so it can't source this file directly -- keep the two in sync by
# hand if either changes; tests/deploy/test-deploy-safety.sh tests this
# copy, which mirrors the embedded one).
#
# `ln -sfn TARGET LINK` does NOT replace LINK if it's already a real file or
# directory -- it creates the symlink *inside* LINK instead, silently. This
# broke a real deploy: the release tarball used to ship some of storage/'s
# subdirectories, so `storage/` already existed as a real (partial)
# directory in the extracted release by the time `ln -sfn shared/storage
# release/storage` ran, leaving storage/framework/views missing and
# Laravel's config/route/view caching failing.
#
# link_shared_paths removes a conflicting real file/directory first --
# always safe, since $release_dir is always a release's own fresh copy,
# never $shared_dir itself -- then symlinks, then verifies the symlink
# actually resolves to the expected shared target (not just "a symlink
# exists").
link_shared_paths() {
  local release_dir="$1" shared_dir="$2"

  for name in .env storage; do
    local target="$release_dir/$name"
    if [ -e "$target" ] && [ ! -L "$target" ]; then
      rm -rf "$target"
    fi
    ln -sfn "$shared_dir/$name" "$target"

    local resolved expected
    resolved="$(readlink -f "$target")"
    expected="$(readlink -f "$shared_dir/$name")"
    if [ "$resolved" != "$expected" ]; then
      echo "ERROR: $target resolved to '$resolved', expected '$expected'"
      return 1
    fi
  done
}
