# Create the "deobfuscated" directory if it doesn't exist and clear it if it does
mkdir -p deobfuscated
rm -rf deobfuscated/*

# Find all the files inside the "toDeobfuscate" directory and loop over them
find toDeobfuscate -type f | while read file; do
    
    # Create the directory structure inside "deobfuscated"
    mkdir -p "deobfuscated/$(dirname "$file" | sed 's/toDeobfuscate\///g')"

    echo $file

    if [[ "$file" == *.php ]]; then
            # run index.php using -f and save the output to the corresponding directory inside "deobfuscated"
            php index.php -f "$file" > "deobfuscated/$(echo "$file" | sed 's/toDeobfuscate\///g')"
        else
            # if the file is not a PHP file, just copy it
            cp "$file" "deobfuscated/$(echo "$file" | sed 's/toDeobfuscate\///g')"
    fi
done

echo "Done decoding!"

echo "Missing files:"
# Compare the "toDeobfuscate" and "deobfuscated" directories and echo a list with any missing files
diff -qr toDeobfuscate deobfuscated | grep "Only in toDeobfuscate:" | awk '{print $4}'