#!/bin/bash

echo "=== COMPREHENSIVE TREASURY SYSTEM TEST ==="
echo ""

# Test 1: Check database schema
echo "1️⃣ Testing Database Schema..."
mysql -u root u734216585_charity -e "SHOW COLUMNS FROM treasury_transactions WHERE Field='source';" 2>/dev/null | grep -q "varchar" && \
  echo "   ✅ Source column is VARCHAR(255)" || \
  echo "   ❌ Source column type is not correct"

# Test 2: Check if any treasuries exist
echo ""
echo "2️⃣ Testing Treasuries..."
count=$(mysql -u root u734216585_charity -e "SELECT COUNT(*) FROM treasuries;" 2>/dev/null | tail -1)
if [ "$count" -gt 0 ]; then
  echo "   ✅ Found $count treasury/treasuries"
  mysql -u root u734216585_charity -e "SELECT id, name, balance FROM treasuries LIMIT 5;" 2>/dev/null | awk 'NR>1 {print "      - " $2 ": " $3 " ج.م"}'
else
  echo "   ⚠️  No treasuries found (test data needed)"
fi

# Test 3: Check routes
echo ""
echo "3️⃣ Testing Laravel Routes..."
php artisan route:list 2>&1 | grep -q "treasury.add-donation" && \
  echo "   ✅ Donation route exists" || \
  echo "   ❌ Donation route missing"

php artisan route:list 2>&1 | grep -q "treasury.perform-transfer" && \
  echo "   ✅ Transfer routes exist" || \
  echo "   ❌ Transfer routes missing"

# Test 4: Check views
echo ""
echo "4️⃣ Testing View Files..."
[ -f "resources/views/treasury/index.blade.php" ] && \
  echo "   ✅ Treasury index view exists" || \
  echo "   ❌ Treasury index view missing"

[ -f "resources/views/treasury/transfer.blade.php" ] && \
  echo "   ✅ Transfer form view exists" || \
  echo "   ❌ Transfer form view missing"

# Test 5: Check controller methods
echo ""
echo "5️⃣ Testing Controller Methods..."
grep -q "public function addDonation" app/Http/Controllers/TreasuryController.php && \
  echo "   ✅ addDonation() method exists" || \
  echo "   ❌ addDonation() method missing"

grep -q "public function performTransfer" app/Http/Controllers/TreasuryController.php && \
  echo "   ✅ performTransfer() method exists" || \
  echo "   ❌ performTransfer() method missing"

# Test 6: Check service methods
echo ""
echo "6️⃣ Testing Service Methods..."
grep -q "public function addDonation" app/Services/TreasuryService.php && \
  echo "   ✅ TreasuryService::addDonation() exists" || \
  echo "   ❌ TreasuryService::addDonation() missing"

grep -q "public function transferBetweenTreasuries" app/Services/TreasuryService.php && \
  echo "   ✅ TreasuryService::transferBetweenTreasuries() exists" || \
  echo "   ❌ TreasuryService::transferBetweenTreasuries() missing"

echo ""
echo "=== TEST COMPLETE ==="
