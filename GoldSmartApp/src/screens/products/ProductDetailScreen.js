import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  Image,
  TouchableOpacity,
  ScrollView,
  ActivityIndicator,
  StyleSheet,
  Alert,
  Dimensions,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { getProduct } from '../../api/products';
import { useCartStore } from '../../store/cartStore';
import { formatCurrency, formatGC, getFullUrl } from '../../utils/helpers';

const { width } = Dimensions.get('window');

export default function ProductDetailScreen({ navigation, route }) {
  const { id } = route.params;
  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  const [quantity, setQuantity] = useState(1);
  const [adding, setAdding] = useState(false);
  const addItem = useCartStore(state => state.addItem);

  useEffect(() => {
    fetchProduct();
  }, [id]);

  const fetchProduct = async () => {
    setLoading(true);
    try {
      const res = await getProduct(id);
      setProduct(res.data?.data || res.data);
    } catch (e) {
      Alert.alert('Error', 'Gagal memuat detail produk');
    } finally {
      setLoading(false);
    }
  };

  const handleAddToCart = async () => {
    setAdding(true);
    try {
      await addItem(product.id, quantity);
      Alert.alert('Berhasil', `${product.name} ditambahkan ke keranjang`);
    } catch (e) {
      Alert.alert('Error', e.response?.data?.message || 'Gagal menambahkan ke keranjang');
    } finally {
      setAdding(false);
    }
  };

  const handleBuyNow = () => {
    navigation.navigate('Checkout', {
      directItem: {
        product_id: product.id,
        product,
        quantity,
      },
    });
  };

  const decreaseQty = () => {
    if (quantity > 1) setQuantity(q => q - 1);
  };

  const increaseQty = () => {
    if (!product.stock || quantity < product.stock) {
      setQuantity(q => q + 1);
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#D4A843" />
      </View>
    );
  }

  if (!product) {
    return (
      <View style={styles.loadingContainer}>
        <Text style={styles.errorText}>Produk tidak ditemukan</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <ScrollView style={styles.scrollView}>
        {product.image ? (
          <Image
            source={{ uri: getFullUrl(product.image) }}
            style={styles.productImage}
            resizeMode="cover"
          />
        ) : (
          <View style={[styles.productImage, styles.placeholder]}>
            <Ionicons name="image-outline" size={80} color="#999" />
          </View>
        )}

        <View style={styles.infoContainer}>
          <Text style={styles.productName}>{product.name}</Text>
          <Text style={styles.productPrice}>{formatCurrency(product.price)}</Text>

          <View style={styles.metaRow}>
            <View style={styles.stockBadge}>
              <Ionicons
                name={product.stock > 0 ? 'checkmark-circle' : 'close-circle'}
                size={16}
                color={product.stock > 0 ? '#28a745' : '#dc3545'}
              />
              <Text style={[styles.stockText, { color: product.stock > 0 ? '#28a745' : '#dc3545' }]}>
                {product.stock > 0 ? `Stok: ${product.stock}` : 'Stok Habis'}
              </Text>
            </View>
          </View>

          {product.gc_bonus > 0 && (
            <View style={styles.gcContainer}>
              <Ionicons name="gift" size={18} color="#D4A843" />
              <Text style={styles.gcText}>Bonus GC: +{formatGC(product.gc_bonus)} GC per item</Text>
            </View>
          )}

          {product.items_per_unit > 0 && (
            <View style={styles.infoRow}>
              <Ionicons name="layers-outline" size={18} color="#666" />
              <Text style={styles.infoRowText}>{product.items_per_unit} item per unit</Text>
            </View>
          )}

          {product.description ? (
            <View style={styles.descriptionContainer}>
              <Text style={styles.descriptionTitle}>Deskripsi</Text>
              <Text style={styles.descriptionText}>{product.description}</Text>
            </View>
          ) : null}
        </View>
      </ScrollView>

      <View style={styles.bottomBar}>
        <View style={styles.quantitySelector}>
          <TouchableOpacity style={styles.qtyBtn} onPress={decreaseQty}>
            <Ionicons name="remove" size={20} color="#333" />
          </TouchableOpacity>
          <Text style={styles.qtyText}>{quantity}</Text>
          <TouchableOpacity style={styles.qtyBtn} onPress={increaseQty}>
            <Ionicons name="add" size={20} color="#333" />
          </TouchableOpacity>
        </View>

        <TouchableOpacity
          style={styles.cartBtn}
          onPress={handleAddToCart}
          disabled={adding || product.stock <= 0}
        >
          {adding ? (
            <ActivityIndicator size="small" color="#D4A843" />
          ) : (
            <Ionicons name="cart-outline" size={22} color="#D4A843" />
          )}
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.buyBtn, product.stock <= 0 && styles.disabledBtn]}
          onPress={handleBuyNow}
          disabled={product.stock <= 0}
        >
          <Text style={styles.buyBtnText}>Beli Langsung</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  errorText: { fontSize: 16, color: '#999' },
  scrollView: { flex: 1 },
  productImage: { width, height: width * 0.8, backgroundColor: '#f0f0f0' },
  placeholder: { justifyContent: 'center', alignItems: 'center' },
  infoContainer: { padding: 16 },
  productName: { fontSize: 20, fontWeight: '700', color: '#333', marginBottom: 8 },
  productPrice: { fontSize: 24, fontWeight: '800', color: '#D4A843', marginBottom: 12 },
  metaRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 12 },
  stockBadge: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  stockText: { fontSize: 14, fontWeight: '500' },
  gcContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#FFF3CD',
    padding: 12,
    borderRadius: 8,
    marginBottom: 12,
  },
  gcText: { fontSize: 14, color: '#856404', fontWeight: '600' },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 12,
  },
  infoRowText: { fontSize: 14, color: '#666' },
  descriptionContainer: { marginTop: 8, paddingTop: 16, borderTopWidth: 1, borderTopColor: '#eee' },
  descriptionTitle: { fontSize: 16, fontWeight: '600', color: '#333', marginBottom: 8 },
  descriptionText: { fontSize: 14, color: '#555', lineHeight: 22 },
  bottomBar: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderTopWidth: 1,
    borderTopColor: '#eee',
    backgroundColor: '#fff',
    gap: 10,
  },
  quantitySelector: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
  },
  qtyBtn: { padding: 10 },
  qtyText: { fontSize: 16, fontWeight: '600', minWidth: 30, textAlign: 'center' },
  cartBtn: {
    padding: 12,
    borderWidth: 1,
    borderColor: '#D4A843',
    borderRadius: 8,
  },
  buyBtn: {
    flex: 1,
    backgroundColor: '#D4A843',
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
  },
  buyBtnText: { color: '#fff', fontSize: 15, fontWeight: '700' },
  disabledBtn: { opacity: 0.5 },
});
