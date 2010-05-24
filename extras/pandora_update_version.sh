#!/bin/bash
# Automatically update Pandora FMS version and build where necessary.

# Check command line arguments
if [ $# -lt 1 ] || [ $# -gt 3 ]; then
	echo "Usage: $0 <final|nightly> <version string> [build string]"
	exit 1
fi

# Set some global vars
if [ "$1" == "nightly" ]; then
	NB=1
else
	NB=0
fi
VERSION=$2
if [ $# == 2 ]; then
	BUILD=`date +%g%m%d`
else
	BUILD=$2
fi
TEMP_FILE="/tmp/pandora_update_version.tmp"
CODE_HOME=~/code/pandora/trunk
CODE_HOME_ENT=~/code/artica/code
SPEC_FILES="$CODE_HOME/pandora_console/pandora_console.spec \
$CODE_HOME/pandora_agents/unix/pandora_agent.spec \
$CODE_HOME/pandora_server/pandora_server.spec \
$CODE_HOME_ENT/pandora/trunk/pandora_console/enterprise/pandora_console_enterprise.spec \
$CODE_HOME_ENT/pandora/trunk/pandora_server/PandoraFMS-Enterprise/pandora_server_enterprise.spec"
DEBIAN_FILES="$CODE_HOME/pandora_console/DEBIAN \
$CODE_HOME/pandora_server/DEBIAN \
$CODE_HOME/pandora_agents/unix/DEBIAN \
$CODE_HOME_ENT/pandora/trunk/pandora_console/DEBIAN \
$CODE_HOME_ENT/pandora/trunk/pandora_server/PandoraFMS-Enterprise/DEBIAN"
SERVER_FILE="$CODE_HOME/pandora_server/lib/PandoraFMS/Config.pm"
CONSOLE_DB_FILE="$CODE_HOME/pandora_console/pandoradb_data.sql"
CONSOLE_FILE="$CODE_HOME/pandora_console/include/config_process.php"
AGENT_UNIX_FILE="$CODE_HOME/pandora_agents/unix/pandora_agent"
AGENT_WIN_FILE="$CODE_HOME/pandora_agents/win32/pandora.cc"

# Update version in spec files
function update_spec_version {
	FILE=$1

	if [ $NB == 1 ]; then
		sed -e "s/^\s*%define\s\s*release\s\s*.*/%define release     $BUILD/" "$FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$FILE"
	else
		sed -e "s/^\s*%define\s\s*release\s\s*.*/%define release     1/" "$FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$FILE"
	fi
	sed -e "s/^\s*%define\s\s*version\s\s*.*/%define version     $VERSION/" "$FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$FILE"
}

# Update version in debian dirs
function update_deb_version {
	DEBIAN_DIR=$1
	
	if [ $NB == 1 ]; then
		LOCAL_VERSION="$VERSION-$BUILD"
	else
		LOCAL_VERSION="$VERSION"
	fi

	sed -e "s/^pandora_version\s*=.*/pandora_version=\"$VERSION\"/" "$DEBIAN_DIR/make_deb_package.sh" > "$TEMP_FILE" && mv "$TEMP_FILE" "$DEBIAN_DIR/make_deb_package.sh" && sed -e "s/^Version:\s*.*/Version: $LOCAL_VERSION/" "$DEBIAN_DIR/control" > "$TEMP_FILE" && mv "$TEMP_FILE" "$DEBIAN_DIR/control"
}

# Spec files
for file in $SPEC_FILES; do
	echo "Updating spec file $file..."
	update_spec_version $file
done

# Debian dirs
for dir in $DEBIAN_FILES; do
	echo "Updating DEBIAN dir $dir..."
	update_deb_version $dir
done

# Pandora Server
echo "Updating Pandora Server version..."
sed -e "s/my\s\s*\$pandora_version\s*=.*/my \$pandora_version = \"$VERSION\";/" "$SERVER_FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$SERVER_FILE"
sed -e "s/my\s\s*\$pandora_build\s*=.*/my \$pandora_build = \"$BUILD\";/" "$SERVER_FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$SERVER_FILE"

# Pandora Console
echo "Updating Pandora Console DB version..."
sed -e "s/\s*[(]\s*'db_scheme_version'\s*\,.*/('db_scheme_version'\,'$VERSION'),/" "$CONSOLE_DB_FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$CONSOLE_DB_FILE"
sed -e "s/\s*[(]\s*'db_scheme_build'\s*\,.*/('db_scheme_build'\,'PD$BUILD'),/" "$CONSOLE_DB_FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$CONSOLE_DB_FILE"
echo "Updating Pandora Console version..."
sed -e "s/\s*\$pandora_version\s*=.*/\$pandora_version = 'v$VERSION';/" "$CONSOLE_FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$CONSOLE_FILE"
sed -e "s/\s*\$build_version\s*=.*/\$build_version = 'PC$BUILD';/" "$CONSOLE_FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$CONSOLE_FILE"

# Pandora Agents
echo "Updating Pandora Unix Agent version..."
sed -e "s/\s*use\s*constant\s*AGENT_VERSION =>.*/use constant AGENT_VERSION => '$VERSION';/" "$AGENT_UNIX_FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$AGENT_UNIX_FILE"
sed -e "s/\s*use\s*constant\s*AGENT_BUILD =>.*/use constant AGENT_BUILD => '$BUILD';/" "$AGENT_UNIX_FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$AGENT_UNIX_FILE"
echo "Updating Pandora Windows Agent version..."
sed -e "s/\s*#define\s*PANDORA_VERSION\s*.*/#define PANDORA_VERSION (\"$VERSION(Build $BUILD)\")/" "$AGENT_WIN_FILE" > "$TEMP_FILE" && mv "$TEMP_FILE" "$AGENT_WIN_FILE"

rm -f "$TEMP_FILE"

