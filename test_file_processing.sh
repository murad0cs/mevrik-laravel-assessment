#!/bin/bash

# File Processing Queue Test Script
# This script demonstrates how to use the file upload and processing endpoints

BASE_URL="https://mindi-unetymologic-keyla.ngrok-free.dev"

echo "=== File Processing Queue Test ==="
echo "Base URL: $BASE_URL"
echo ""

# Test 1: Upload a text file for transformation
echo "1. Uploading text file for transformation..."
response=$(curl -s -X POST "$BASE_URL/api/queue/upload-file" \
  -F "file=@test_file_examples/sample.txt" \
  -F "processing_type=text_transform" \
  -F "user_id=123")

echo "Response: $response"
file_id=$(echo $response | grep -oP '"file_id"\s*:\s*"\K[^"]+')
echo "File ID: $file_id"
echo ""

# Test 2: Check processing status
echo "2. Checking processing status..."
sleep 2
curl -s "$BASE_URL/api/queue/file-status/$file_id" | json_pp
echo ""

# Test 3: Upload CSV for analysis
echo "3. Uploading CSV file for analysis..."
response=$(curl -s -X POST "$BASE_URL/api/queue/upload-file" \
  -F "file=@test_file_examples/sample.csv" \
  -F "processing_type=csv_analyze" \
  -F "user_id=456")

csv_file_id=$(echo $response | grep -oP '"file_id"\s*:\s*"\K[^"]+')
echo "CSV File ID: $csv_file_id"
echo ""

# Test 4: Upload JSON for formatting
echo "4. Uploading JSON file for formatting..."
response=$(curl -s -X POST "$BASE_URL/api/queue/upload-file" \
  -F "file=@test_file_examples/sample.json" \
  -F "processing_type=json_format" \
  -F "user_id=789")

json_file_id=$(echo $response | grep -oP '"file_id"\s*:\s*"\K[^"]+')
echo "JSON File ID: $json_file_id"
echo ""

# Wait for processing
echo "5. Waiting for processing to complete..."
sleep 5

# Test 5: Download processed files
echo "6. Downloading processed text file..."
curl -s -o "processed_text.txt" "$BASE_URL/api/queue/download/$file_id"
echo "Downloaded to: processed_text.txt"

echo "7. Downloading processed CSV analysis..."
curl -s -o "processed_csv.txt" "$BASE_URL/api/queue/download/$csv_file_id"
echo "Downloaded to: processed_csv.txt"

echo "8. Downloading processed JSON..."
curl -s -o "processed_json.txt" "$BASE_URL/api/queue/download/$json_file_id"
echo "Downloaded to: processed_json.txt"

echo ""
echo "=== Test Complete ==="
echo "Check the downloaded files to see the processed results!"