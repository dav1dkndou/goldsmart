import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  TextInput,
  FlatList,
  TouchableOpacity,
  Image,
  ScrollView,
  ActivityIndicator,
  RefreshControl,
  StyleSheet,
  Alert,
  Dimensions,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { getProducts, getCategories } from '../../api/products';
import { formatCurrency, formatGC, getFullUrl } from '../../utils/helpers';

const { width } = Dimensions.get('window');
const ITEM_WIDTH = (width - 36) / 2;

export default function ProductListScreen({ navigation, route }) {
  const initialCategory = route?.params?.category || null;
  const initialSearch = route?.params?.search || '';

  const [products, setProducts] = useState([]);
  const [categories, setCategoriesList] = useState([]);
  const [search, setSearch] = useState(initialSearch);
  const [selectedCategory, setSelectedCategory] = useState(initialCategory);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const fetchCategories = async () => {
    try {
      const res = await getCategories();
      setCategoriesList(res.data?.data || res.data || []);
    } catch (e) {
      console.log('Failed to load categories', e);
    }
  };

  const fetchProducts = useCallback(async (pageNum = 1, reset = false) => {
    setLoading(true);
    try {
      const params = { page: pageNum };
      if (search) params.search = search;
      if (selectedCategory) params.category = selectedCategory;
      const res = await getProducts(params);
      const data = res.data?.data?.data || res.data?.data || res.data || [];
      const lastPage = res.data?.data?.last_page || res.data?.last_page || 1;
      if (reset) {
        setProducts(data);
      } else {
        setProducts(prev => [...prev, ...data]);
      }
      setHasMore(pageNum < lastPage);
      setPage(pageNum);
    } catch (e) {
      Alert.alert('Error', 'Gagal memuat produk');
    } finally {
      setLoading(false);
    }
  }, [search, selectedCategory]);

  useEffect(() => {
    fetchCategories();
  }, []);

  useEffect(() => {
    fetchProducts(1, true);
  }, [selectedCategory]);

  const handleSearch = () => {
    fetchProducts(1, true);
  };

  const handleRefresh = async () => {
    setRefreshing(true);
    await fetchProducts(1, true);
    setRefreshing(false);
  };

  const handleEndReached = () => {
    if (hasMore) {
      fetchProducts(page + 1, false);
    }
  };

  const renderProduct = useCallback(({ item }) => (
    <ProductItem item={item} onPress={() => navigation.navigate('ProductDetail', { id: item.id })} />
  ), [navigation]);

  const keyExtractor = useCallback(item => item.id.toString(), []);

  return (
    <View style={styles.container}>
      <View style={styles.searchContainer}>
        <Ionicons name="search" size={20} color="#999" style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          placeholder="Cari produk..."
          value={search}
          onChangeText={setSearch}
          onSubmitEditing={handleSearch}
          returnKeyType="search"
        />
        {search.length > 0 && (
          <TouchableOpacity onPress={() => { setSearch(''); setTimeout(() => fetchProducts(1, true), 100); }}>
            <Ionicons name="close-circle" size={20} color="#999" />
          </TouchableOpacity>
        )}
      </View>

      {categories.length > 0 && (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.categoryScroll} contentContainerStyle={styles.categoryContent}>
          <TouchableOpacity
            style={[styles.categoryChip, !selectedCategory && styles.categoryChipActive]}
            onPress={() => setSelectedCategory(null)}
          >
            <Text style={[styles.categoryChipText, !selectedCategory && styles.categoryChipTextActive]}>Semua</Text>
          </TouchableOpacity>
          {categories.map(cat => (
            <TouchableOpacity
              key={cat.id}
              style={[styles.categoryChip, selectedCategory === cat.slug && styles.categoryChipActive]}
              onPress={() => setSelectedCategory(cat.slug)}
            >
              <Text style={[styles.categoryChipText, selectedCategory === cat.slug && styles.categoryChipTextActive]}>
                {cat.name}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      )}

      <FlatList
        data={products}
        renderItem={renderProduct}
        keyExtractor={keyExtractor}
        numColumns={2}
        columnWrapperStyle={styles.row}
        contentContainerStyle={styles.listContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={handleRefresh} />}
        onEndReached={handleEndReached}
        onEndReachedThreshold={0.5}
        initialNumToRender={10}
        maxToRenderPerBatch={10}
        windowSize={5}
        removeClippedSubviews={true}
        ListFooterComponent={loading && !refreshing ? <ActivityIndicator style={{ padding: 16 }} /> : null}
        ListEmptyComponent={
          !loading && (
            <View style={styles.emptyContainer}>
              <Ionicons name="cube-outline" size={60} color="#ccc" />
              <Text style={styles.emptyText}>Produk tidak ditemukan</Text>
            </View>
          )
        }
      />
    </View>
  );
}

const ProductItem = React.memo(({ item, onPress }) => {
  const [imgError, setImgError] = useState(false);
  const imageUrl = item.image && item.image.trim() !== '' ? getFullUrl(item.image) : null;

  return (
    <TouchableOpacity style={styles.productCard} onPress={onPress}>
      {imageUrl && !imgError ? (
        <Image
          source={{ uri: imageUrl }}
          style={styles.productImage}
          resizeMode="cover"
          onError={() => setImgError(true)}
        />
      ) : (
        <View style={[styles.productImage, styles.placeholder]}>
          <Ionicons name="image-outline" size={40} color="#999" />
        </View>
      )}
      <View style={styles.productInfo}>
        <Text style={styles.productName} numberOfLines={2}>{item.name}</Text>
        <Text style={styles.productPrice}>{formatCurrency(item.price)}</Text>
        {item.gc_bonus > 0 && (
          <View style={styles.gcBadge}>
            <Text style={styles.gcBadgeText}>+{formatGC(item.gc_bonus)}</Text>
          </View>
        )}
      </View>
    </TouchableOpacity>
  );
});

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    margin: 12,
    borderRadius: 8,
    paddingHorizontal: 12,
    elevation: 2,
  },
  searchIcon: { marginRight: 8 },
  searchInput: { flex: 1, height: 44, fontSize: 15 },
  categoryScroll: { flexGrow: 0, marginBottom: 16, minHeight: 45 },
  categoryContent: { paddingHorizontal: 12, paddingVertical: 8, alignItems: 'center' },
  categoryChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#ddd',
    marginRight: 8,
    justifyContent: 'center',
    alignItems: 'center',
    minHeight: 36,
  },
  categoryChipActive: { backgroundColor: '#D4A843', borderColor: '#D4A843' },
  categoryChipText: { fontSize: 14, color: '#666', includeFontPadding: false },
  categoryChipTextActive: { color: '#fff', fontWeight: '600' },
  listContent: { paddingHorizontal: 12, paddingBottom: 16 },
  row: { justifyContent: 'space-between', marginBottom: 12 },
  productCard: {
    width: ITEM_WIDTH,
    backgroundColor: '#fff',
    borderRadius: 10,
    overflow: 'hidden',
    elevation: 2,
  },
  productImage: { width: '100%', height: ITEM_WIDTH, backgroundColor: '#f0f0f0' },
  placeholder: { justifyContent: 'center', alignItems: 'center' },
  productInfo: { padding: 10 },
  productName: { fontSize: 14, fontWeight: '500', color: '#333', marginBottom: 4 },
  productPrice: { fontSize: 15, fontWeight: '700', color: '#D4A843' },
  gcBadge: {
    marginTop: 4,
    backgroundColor: '#FFF3CD',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
    alignSelf: 'flex-start',
  },
  gcBadgeText: { fontSize: 11, color: '#856404', fontWeight: '600' },
  emptyContainer: { alignItems: 'center', paddingTop: 60 },
  emptyText: { fontSize: 15, color: '#999', marginTop: 12 },
});
