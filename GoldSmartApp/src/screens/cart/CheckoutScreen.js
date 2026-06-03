import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../../store/authStore';
import { useCartStore } from '../../store/cartStore';
import { getCheckoutStatus } from '../../api/cart';
import { formatCurrency, formatGC } from '../../utils/helpers';

export default function CheckoutScreen({ navigation, route }) {
  const { items: cartItems, fetchCart, checkout } = useCartStore();
  const [checkoutStatus, setCheckoutStatus] = useState(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const directItem = route?.params?.directItem || null;

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      if (!directItem) {
        await fetchCart();
      }
      const statusRes = await getCheckoutStatus();
      setCheckoutStatus(statusRes.data?.data || statusRes.data);
    } catch (e) {
      console.log('Failed to load checkout data', e);
    } finally {
      setLoading(false);
    }
  };

  const items = directItem
    ? [{ ...directItem, product: directItem.product, price: directItem.product?.price || 0, gc_bonus: directItem.product?.gc_bonus || 0 }]
    : (Array.isArray(cartItems) ? cartItems : (cartItems?.items || []));

  const totalPrice = items.reduce((sum, item) => {
    const price = item.price || item.product?.price || 0;
    return sum + price * item.quantity;
  }, 0);

  const totalGC = items.reduce((sum, item) => {
    const gc = item.gc_reward || item.gc_bonus || item.product?.gc_bonus || 0;
    return sum + gc * item.quantity;
  }, 0);

  const canCheckout = !checkoutStatus?.daily_limit_reached && !checkoutStatus?.cooldown_active;

  const handleCheckout = async () => {
    if (!canCheckout) {
      Alert.alert('Tidak Dapat Checkout', 'Anda telah mencapai batas harian atau dalam masa cooldown.');
      return;
    }

    setSubmitting(true);
    try {
      if (directItem) {
        await checkout({ product_id: directItem.product_id, quantity: directItem.quantity });
      } else {
        await checkout();
      }
      useAuthStore.getState().fetchUser();
      Alert.alert('Berhasil', 'Pesanan berhasil!', [
        { text: 'OK', onPress: () => navigation.navigate('TransactionList') },
      ]);
    } catch (e) {
      Alert.alert('Error', e.response?.data?.message || 'Gagal melakukan checkout');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#D4A843" />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <ScrollView style={styles.scrollView}>
        <Text style={styles.sectionTitle}>Ringkasan Pesanan</Text>

        {items.map((item, index) => {
          const product = item.product || {};
          const name = product.name || item.product_name || item.name || 'Produk';
          const price = item.price || product.price || 0;

          return (
            <View key={index} style={styles.orderItem}>
              <View style={styles.orderItemLeft}>
                <Text style={styles.orderItemName}>{name}</Text>
                <Text style={styles.orderItemQty}>x{item.quantity}</Text>
              </View>
              <Text style={styles.orderItemPrice}>{formatCurrency(price * item.quantity)}</Text>
            </View>
          );
        })}

        <View style={styles.divider} />

        <View style={styles.totalRow}>
          <Text style={styles.totalLabel}>Total Harga</Text>
          <Text style={styles.totalPrice}>{formatCurrency(totalPrice)}</Text>
        </View>

        {totalGC > 0 && (
          <View style={styles.totalRow}>
            <Text style={styles.totalLabel}>Total GC Reward</Text>
            <Text style={styles.totalGC}>+{formatGC(totalGC)} GC</Text>
          </View>
        )}

        {checkoutStatus?.daily_limit_reached && (
          <View style={styles.warningBox}>
            <Ionicons name="warning" size={20} color="#856404" />
            <Text style={styles.warningText}>
              Anda telah mencapai batas transaksi harian.
            </Text>
          </View>
        )}

        {checkoutStatus?.cooldown_active && (
          <View style={styles.warningBox}>
            <Ionicons name="time" size={20} color="#856404" />
            <Text style={styles.warningText}>
              Cooldown aktif. Silakan coba beberapa saat lagi.
            </Text>
          </View>
        )}
      </ScrollView>

      <View style={styles.bottomBar}>
        <View style={styles.bottomInfo}>
          <Text style={styles.bottomLabel}>Total</Text>
          <Text style={styles.bottomPrice}>{formatCurrency(totalPrice)}</Text>
        </View>
        <TouchableOpacity
          style={[styles.confirmBtn, (!canCheckout || submitting) && styles.disabledBtn]}
          onPress={handleCheckout}
          disabled={!canCheckout || submitting}
        >
          {submitting ? (
            <ActivityIndicator size="small" color="#fff" />
          ) : (
            <Text style={styles.confirmBtnText}>Konfirmasi Pesanan</Text>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  centerContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  scrollView: { flex: 1, padding: 16 },
  sectionTitle: { fontSize: 18, fontWeight: '700', color: '#333', marginBottom: 16 },
  orderItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#fff',
    padding: 14,
    borderRadius: 8,
    marginBottom: 8,
  },
  orderItemLeft: { flex: 1 },
  orderItemName: { fontSize: 14, fontWeight: '500', color: '#333' },
  orderItemQty: { fontSize: 13, color: '#666', marginTop: 2 },
  orderItemPrice: { fontSize: 14, fontWeight: '600', color: '#333' },
  divider: { height: 1, backgroundColor: '#ddd', marginVertical: 16 },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  totalLabel: { fontSize: 15, color: '#666' },
  totalPrice: { fontSize: 18, fontWeight: '700', color: '#D4A843' },
  totalGC: { fontSize: 15, fontWeight: '600', color: '#856404' },
  warningBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#FFF3CD',
    padding: 14,
    borderRadius: 8,
    marginTop: 16,
  },
  warningText: { flex: 1, fontSize: 13, color: '#856404' },
  bottomBar: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#fff',
    borderTopWidth: 1,
    borderTopColor: '#eee',
    gap: 12,
  },
  bottomInfo: { flex: 1 },
  bottomLabel: { fontSize: 13, color: '#666' },
  bottomPrice: { fontSize: 18, fontWeight: '700', color: '#D4A843' },
  confirmBtn: {
    backgroundColor: '#D4A843',
    paddingHorizontal: 24,
    paddingVertical: 14,
    borderRadius: 8,
  },
  confirmBtnText: { color: '#fff', fontSize: 15, fontWeight: '700' },
  disabledBtn: { opacity: 0.5 },
});
