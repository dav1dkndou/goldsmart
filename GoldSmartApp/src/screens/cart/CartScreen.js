import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  Image,
  ActivityIndicator,
  RefreshControl,
  StyleSheet,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useCartStore } from '../../store/cartStore';
import { formatCurrency, formatGC, getFullUrl } from '../../utils/helpers';

export default function CartScreen({ navigation }) {
  const { items: cartItems, loading, fetchCart, updateItem, removeItem } = useCartStore();
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    fetchCart();
  }, []);

  const handleRefresh = async () => {
    setRefreshing(true);
    await fetchCart();
    setRefreshing(false);
  };

  const handleUpdateQty = async (id, newQty) => {
    if (newQty < 1) return;
    try {
      await updateItem(id, newQty);
    } catch (e) {
      Alert.alert('Error', e.response?.data?.message || 'Gagal mengubah jumlah');
    }
  };

  const handleRemove = (id, name) => {
    Alert.alert(
      'Hapus Item',
      `Hapus ${name} dari keranjang?`,
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Hapus',
          style: 'destructive',
          onPress: async () => {
            try {
              await removeItem(id);
            } catch (e) {
              Alert.alert('Error', 'Gagal menghapus item');
            }
          },
        },
      ],
    );
  };

  const items = Array.isArray(cartItems) ? cartItems : (cartItems?.items || []);
  const totalItems = items.reduce((sum, item) => sum + item.quantity, 0);
  const totalPrice = items.reduce((sum, item) => sum + (item.price || item.product?.price || 0) * item.quantity, 0);
  const totalGC = items.reduce((sum, item) => sum + ((item.gc_reward || item.gc_bonus || item.product?.gc_bonus || 0) * item.quantity), 0);

  const renderItem = ({ item }) => {
    const product = item.product || {};
    const name = product.name || item.product_name || item.name || 'Produk';
    const price = item.price || product.price || 0;
    const image = product.image || item.image;
    const gcBonus = item.gc_reward || item.gc_bonus || product.gc_bonus || 0;

    const imageUrl = image && image.trim() !== '' ? getFullUrl(image) : null;

    return (
      <View style={styles.cartItem}>
        {imageUrl ? (
          <Image
            source={{ uri: imageUrl }}
            style={styles.itemImage}
            resizeMode="cover"
          />
        ) : (
          <View style={[styles.itemImage, styles.placeholder]}>
            <Ionicons name="image-outline" size={24} color="#999" />
          </View>
        )}

        <View style={styles.itemInfo}>
          <Text style={styles.itemName} numberOfLines={2}>{name}</Text>
          <Text style={styles.itemPrice}>{formatCurrency(price)}</Text>
          {gcBonus > 0 && (
            <Text style={styles.itemGC}>+{formatGC(gcBonus)} GC</Text>
          )}

          <View style={styles.qtyRow}>
            <TouchableOpacity
              style={styles.qtyBtn}
              onPress={() => handleUpdateQty(item.id, item.quantity - 1)}
            >
              <Ionicons name="remove" size={16} color="#333" />
            </TouchableOpacity>
            <Text style={styles.qtyText}>{item.quantity}</Text>
            <TouchableOpacity
              style={styles.qtyBtn}
              onPress={() => handleUpdateQty(item.id, item.quantity + 1)}
            >
              <Ionicons name="add" size={16} color="#333" />
            </TouchableOpacity>

            <Text style={styles.subtotal}>{formatCurrency(price * item.quantity)}</Text>
          </View>
        </View>

        <TouchableOpacity
          style={styles.removeBtn}
          onPress={() => handleRemove(item.id, name)}
        >
          <Ionicons name="trash-outline" size={20} color="#dc3545" />
        </TouchableOpacity>
      </View>
    );
  };

  if (loading && items.length === 0) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#D4A843" />
      </View>
    );
  }

  if (items.length === 0) {
    return (
      <View style={styles.centerContainer}>
        <Ionicons name="cart-outline" size={80} color="#ccc" />
        <Text style={styles.emptyText}>Keranjang kosong</Text>
        <TouchableOpacity
          style={styles.shopBtn}
          onPress={() => navigation.navigate('ProductsTab', { screen: 'ProductList' })}
        >
          <Text style={styles.shopBtnText}>Belanja Sekarang</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={items}
        renderItem={renderItem}
        keyExtractor={(item, index) => item.id ? `${item.id}-${index}` : index.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={handleRefresh} />}
      />

      <View style={styles.summaryContainer}>
        <View style={styles.summaryRow}>
          <Text style={styles.summaryLabel}>Total Item</Text>
          <Text style={styles.summaryValue}>{totalItems}</Text>
        </View>
        <View style={styles.summaryRow}>
          <Text style={styles.summaryLabel}>Total Harga</Text>
          <Text style={styles.summaryPrice}>{formatCurrency(totalPrice)}</Text>
        </View>
        {totalGC > 0 && (
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Total GC Bonus</Text>
            <Text style={styles.summaryGC}>+{formatGC(totalGC)} GC</Text>
          </View>
        )}

        <TouchableOpacity
          style={styles.checkoutBtn}
          onPress={() => navigation.navigate('Checkout')}
        >
          <Text style={styles.checkoutBtnText}>Checkout</Text>
          <Ionicons name="arrow-forward" size={20} color="#fff" />
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  centerContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f5f5f5' },
  emptyText: { fontSize: 16, color: '#999', marginTop: 12, marginBottom: 20 },
  shopBtn: {
    backgroundColor: '#D4A843',
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
  },
  shopBtnText: { color: '#fff', fontSize: 15, fontWeight: '600' },
  listContent: { padding: 12, paddingBottom: 8 },
  cartItem: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 12,
    marginBottom: 10,
    elevation: 1,
  },
  itemImage: { width: 70, height: 70, borderRadius: 8, backgroundColor: '#f0f0f0' },
  placeholder: { justifyContent: 'center', alignItems: 'center' },
  itemInfo: { flex: 1, marginLeft: 12 },
  itemName: { fontSize: 14, fontWeight: '500', color: '#333' },
  itemPrice: { fontSize: 14, color: '#D4A843', fontWeight: '600', marginTop: 2 },
  itemGC: { fontSize: 12, color: '#856404', marginTop: 2 },
  qtyRow: { flexDirection: 'row', alignItems: 'center', marginTop: 8 },
  qtyBtn: {
    width: 28,
    height: 28,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 6,
    justifyContent: 'center',
    alignItems: 'center',
  },
  qtyText: { fontSize: 14, fontWeight: '600', marginHorizontal: 10 },
  subtotal: { fontSize: 13, color: '#666', marginLeft: 'auto' },
  removeBtn: { padding: 4, alignSelf: 'flex-start' },
  summaryContainer: {
    backgroundColor: '#fff',
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: '#eee',
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  summaryLabel: { fontSize: 14, color: '#666' },
  summaryValue: { fontSize: 14, fontWeight: '600', color: '#333' },
  summaryPrice: { fontSize: 16, fontWeight: '700', color: '#D4A843' },
  summaryGC: { fontSize: 14, fontWeight: '600', color: '#856404' },
  checkoutBtn: {
    flexDirection: 'row',
    backgroundColor: '#D4A843',
    paddingVertical: 14,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 8,
    gap: 8,
  },
  checkoutBtnText: { color: '#fff', fontSize: 16, fontWeight: '700' },
});
