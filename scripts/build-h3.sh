#!/bin/bash

# Build script for H3 shared library
# This script downloads and compiles the H3 library from source
# and places the compiled shared library in the bin/<platform>/ directory

set -e

# Get the script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BIN_DIR="$PROJECT_ROOT/bin"
BUILD_DIR="$PROJECT_ROOT/build-h3-temp"
H3_REPO="https://github.com/uber/h3"

# Extract H3 version from H3.php if not set via environment variable
get_h3_version() {
    if [[ -n "$H3_VERSION" ]]; then
        echo "$H3_VERSION"
        return
    fi

    local h3_php="$PROJECT_ROOT/src/H3.php"
    if [[ -f "$h3_php" ]]; then
        local version=$(grep -oP "public const H3_VERSION = '\K[^']+" "$h3_php" 2>/dev/null || \
                       grep "public const H3_VERSION" "$h3_php" | sed "s/.*'\([^']*\)'.*/\1/")
        if [[ -n "$version" ]]; then
            echo "$version"
            return
        fi
    fi

    # Fallback version
    echo "4.4.1"
}

# Detect OS
detect_os() {
    case "$(uname -s)" in
        Darwin*)    echo "darwin" ;;
        Linux*)     echo "linux" ;;
        CYGWIN*|MINGW*|MSYS*) echo "windows" ;;
        *)          echo "unknown" ;;
    esac
}

# Detect architecture
detect_arch() {
    case "$(uname -m)" in
        x86_64|amd64)   echo "x64" ;;
        arm64|aarch64)  echo "arm64" ;;
        *)              echo "$(uname -m)" ;;
    esac
}

H3_VERSION=$(get_h3_version)
OS=$(detect_os)
ARCH=$(detect_arch)
PLATFORM_DIR="$OS-$ARCH"

echo "=========================================="
echo "H3 Library Build Script"
echo "=========================================="
echo "H3 Version: $H3_VERSION"
echo "OS: $OS"
echo "Architecture: $ARCH"
echo "Platform Directory: $PLATFORM_DIR"
echo "Project Root: $PROJECT_ROOT"
echo "=========================================="

# Check dependencies
check_dependencies() {
    echo "Checking dependencies..."

    local missing_deps=()

    if ! command -v git &> /dev/null; then
        missing_deps+=("git")
    fi

    if ! command -v cmake &> /dev/null; then
        missing_deps+=("cmake")
    fi

    if ! command -v make &> /dev/null && [[ "$OS" != "windows" ]]; then
        missing_deps+=("make")
    fi

    if [[ ${#missing_deps[@]} -gt 0 ]]; then
        echo "Error: Missing dependencies: ${missing_deps[*]}"
        echo ""
        echo "Please install them:"
        case "$OS" in
            darwin)
                echo "  brew install ${missing_deps[*]}"
                ;;
            linux)
                echo "  sudo apt install ${missing_deps[*]}"
                echo "  or"
                echo "  sudo yum install ${missing_deps[*]}"
                ;;
        esac
        exit 1
    fi

    echo "All dependencies found."
}

# Clean up previous build
cleanup() {
    echo "Cleaning up previous build..."
    rm -rf "$BUILD_DIR"
}

# Clone H3 repository
clone_h3() {
    echo "Cloning H3 repository (version $H3_VERSION)..."
    git clone --depth 1 --branch "v$H3_VERSION" "$H3_REPO" "$BUILD_DIR"
}

# Build H3
build_h3() {
    echo "Building H3..."

    cd "$BUILD_DIR"
    mkdir -p build
    cd build

    # Configure with CMake (disable tests to avoid linker issues)
    if [[ "$OS" == "darwin" ]]; then
        cmake -DCMAKE_BUILD_TYPE=Release \
              -DBUILD_SHARED_LIBS=ON \
              -DBUILD_TESTING=OFF \
              -DENABLE_FORMAT=OFF \
              -DENABLE_DOCS=OFF \
              -DENABLE_LINTING=OFF \
              ..
    elif [[ "$OS" == "linux" ]]; then
        cmake -DCMAKE_BUILD_TYPE=Release \
              -DBUILD_SHARED_LIBS=ON \
              -DBUILD_TESTING=OFF \
              -DENABLE_FORMAT=OFF \
              -DENABLE_DOCS=OFF \
              -DENABLE_LINTING=OFF \
              -DCMAKE_POSITION_INDEPENDENT_CODE=ON \
              ..
    else
        cmake -DCMAKE_BUILD_TYPE=Release \
              -DBUILD_SHARED_LIBS=ON \
              -DBUILD_TESTING=OFF \
              -DENABLE_FORMAT=OFF \
              -DENABLE_DOCS=OFF \
              -DENABLE_LINTING=OFF \
              ..
    fi

    # Build only the h3 library target
    cmake --build . --config Release --target h3 -j$(nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || echo 4)
}

# Copy library to bin directory
copy_library() {
    echo "Copying library to bin/$PLATFORM_DIR/..."

    local target_dir="$BIN_DIR/$PLATFORM_DIR"
    mkdir -p "$target_dir"

    case "$OS" in
        darwin)
            local lib_file="$BUILD_DIR/build/lib/libh3.dylib"
            if [[ -f "$lib_file" ]]; then
                cp "$lib_file" "$target_dir/libh3.dylib"
                echo "Copied: $target_dir/libh3.dylib"
            else
                lib_file=$(find "$BUILD_DIR/build" -name "libh3*.dylib" -type f | head -1)
                if [[ -n "$lib_file" ]]; then
                    cp "$lib_file" "$target_dir/libh3.dylib"
                    echo "Copied: $target_dir/libh3.dylib"
                else
                    echo "Error: Could not find libh3.dylib"
                    exit 1
                fi
            fi
            ;;
        linux)
            local lib_file="$BUILD_DIR/build/lib/libh3.so"
            if [[ -f "$lib_file" ]]; then
                cp "$lib_file" "$target_dir/libh3.so"
                echo "Copied: $target_dir/libh3.so"
            else
                lib_file=$(find "$BUILD_DIR/build" -name "libh3.so*" -type f | head -1)
                if [[ -n "$lib_file" ]]; then
                    cp "$lib_file" "$target_dir/libh3.so"
                    echo "Copied: $target_dir/libh3.so"
                else
                    echo "Error: Could not find libh3.so"
                    exit 1
                fi
            fi
            ;;
        windows)
            local lib_file=$(find "$BUILD_DIR/build" -name "h3.dll" -type f | head -1)
            if [[ -n "$lib_file" ]]; then
                cp "$lib_file" "$target_dir/h3.dll"
                echo "Copied: $target_dir/h3.dll"
            else
                echo "Error: Could not find h3.dll"
                exit 1
            fi
            ;;
    esac
}

# Final cleanup
final_cleanup() {
    echo "Cleaning up build directory..."
    rm -rf "$BUILD_DIR"
}

# Main
main() {
    check_dependencies
    cleanup
    clone_h3
    build_h3
    copy_library
    final_cleanup

    echo ""
    echo "=========================================="
    echo "Build completed successfully!"
    echo "Library location: $BIN_DIR/$PLATFORM_DIR"
    echo "H3 Version: $H3_VERSION"
    echo "=========================================="
}

main "$@"
