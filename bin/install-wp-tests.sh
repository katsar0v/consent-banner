#!/usr/bin/env bash
set -euo pipefail

database_name="${1:-wordpress_test}"
database_user="${2:-root}"
database_password="${3:-root}"
database_host="${4:-127.0.0.1}"
wordpress_version="${5:-latest}"
skip_database="${6:-false}"
wordpress_dir=/tmp/wordpress
tests_dir=/tmp/wordpress-tests-lib
develop_dir=/tmp/wordpress-develop

rm -rf -- "${wordpress_dir}" "${tests_dir}" "${develop_dir}"
mkdir -p "${wordpress_dir}" "${tests_dir}" "${develop_dir}"

if [[ "${wordpress_version}" == latest ]]; then
  archive_url=https://wordpress.org/latest.tar.gz
  develop_archive=https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.tar.gz
else
  archive_url="https://wordpress.org/wordpress-${wordpress_version}.tar.gz"
  develop_archive="https://github.com/WordPress/wordpress-develop/archive/refs/tags/${wordpress_version}.0.tar.gz"
fi

curl --fail --silent --show-error --location "${archive_url}" | tar --strip-components=1 -xz -C "${wordpress_dir}"
curl --fail --silent --show-error --location "${develop_archive}" | tar --strip-components=1 -xz -C "${develop_dir}"
cp -R "${develop_dir}/tests/phpunit/includes" "${tests_dir}/includes"
cp -R "${develop_dir}/tests/phpunit/data" "${tests_dir}/data"
cp "${develop_dir}/wp-tests-config-sample.php" "${tests_dir}/wp-tests-config.php"

sed -i "s/youremptytestdbnamehere/${database_name}/" "${tests_dir}/wp-tests-config.php"
sed -i "s/yourusernamehere/${database_user}/" "${tests_dir}/wp-tests-config.php"
sed -i "s/yourpasswordhere/${database_password}/" "${tests_dir}/wp-tests-config.php"
sed -i "s|localhost|${database_host}|" "${tests_dir}/wp-tests-config.php"
sed -i "s|dirname( __FILE__ ) . '/src/'|'${wordpress_dir}/'|" "${tests_dir}/wp-tests-config.php"

if [[ "${skip_database}" != true ]]; then
  mysqladmin create "${database_name}" --host="${database_host}" --user="${database_user}" --password="${database_password}" || true
fi
