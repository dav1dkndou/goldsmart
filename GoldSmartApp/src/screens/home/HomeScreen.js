import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  Image,
  Alert,
  ActivityIndicator,
  RefreshControl,
  FlatList,
  StyleSheet,
  Dimensions,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../../store/authStore';
import { getFeaturedProducts, getCategories } from '../../api/products';
import { getDailyBonus, claimDailyBonus } from '../../api/mining';
import { formatCurrency, formatGC, getFullUrl } from '../../utils/helpers';

const { width } = Dimensions.get('window');

export default function HomeScreen({ navigation }) {
  const user = useAuthStore((s) => s.user);
  const [featuredProducts, setFeaturedProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [dailyBonus, setDailyBonus] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [claiming, setClaiming] = useState(false);

  const fetchData = async () => {
    try {
      const [featuredRes, categoriesRes, bonusRes] = await Promise.allSettled([
        getFeaturedProducts(),
        getCategories(),
        getDailyBonus(),
      ]);
      if (featuredRes.status === 'fulfilled') {
        setFeaturedProducts(featuredRes.value.data?.data || []);
      }
      if (categoriesRes.status === 'fulfilled') {
        setCategories(categoriesRes.value.data?.data || []);
      }
      if (bonusRes.status === 'fulfilled') {
        setDailyBonus(bonusRes.value.data?.data || null);
      }
    } catch (e) {
      // silent
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchData();
  }, []);

  const handleClaimBonus = async () => {
    try {
      setClaiming(true);
      const res = await claimDailyBonus();
      Alert.alert('Berhasil', res.data?.message || 'Bonus harian berhasil diklaim!');
      setDailyBonus((prev) => (prev ? { ...prev, claimed: true } : prev));
      useAuthStore.getState().fetchUser();
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal klaim bonus.';
      Alert.alert('Gagal', msg);
    } finally {
      setClaiming(false);
    }
  };

  const quickActions = [
    { key: 'Mining', icon: 'hardware-chip-outline', label: 'Mining', tab: 'MiningTab' },
    { key: 'Membership', icon: 'shield-outline', label: 'Member', tab: 'ProfileTab', screen: 'Membership' },
    { key: 'Withdrawal', icon: 'wallet-outline', label: 'Withdrawal', tab: 'ProfileTab', screen: 'WithdrawalForm' },
    { key: 'Referral', icon: 'people-outline', label: 'Referral', tab: 'ProfileTab', screen: 'Referral' },
  ];

  const ProductItem = React.memo(({ item, onPress }) => {
    const [imgError, setImgError] = useState(false);
    const imageUrl = item.image && item.image.trim() !== '' ? getFullUrl(item.image) : null;

    return (
      <TouchableOpacity style={styles.productCard} onPress={onPress}>
        {imageUrl && !imgError ? (
          <Image
            source={{ uri: imageUrl }}
            style={styles.productImage}
            onError={() => setImgError(true)}
          />
        ) : (
          <View style={styles.productImagePlaceholder}>
            <Ionicons name="cube-outline" size={32} color="#999" />
          </View>
        )}
        <Text style={styles.productName} numberOfLines={2}>{item.name}</Text>
        <Text style={styles.productPrice}>{formatCurrency(item.price)}</Text>
        {item.gc_bonus != null && (
          <Text style={styles.productGC}>+{formatGC(item.gc_bonus)}</Text>
        )}
      </TouchableOpacity>
    );
  });

  const renderProductItem = ({ item }) => (
    <ProductItem item={item} onPress={() => navigation.navigate('ProductDetail', { id: item.id })} />
  );

  const renderCategoryItem = ({ item }) => (
    <TouchableOpacity
      style={styles.categoryChip}
      onPress={() => navigation.navigate('ProductsTab', { screen: 'ProductList', params: { category: item.slug } })}
    >
      <Text style={styles.categoryText}>{item.name}</Text>
    </TouchableOpacity>
  );

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#DAA520" />
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#DAA520" />
      }
    >
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.greeting}>Selamat datang,</Text>
          <Text style={styles.userName}>{user?.name || 'User'}</Text>
        </View>
        <View style={styles.balanceBadge}>
          <Ionicons name="diamond-outline" size={16} color="#DAA520" />
          <Text style={styles.balanceText}>
            {formatGC(user?.gc_balance ?? 0)}
          </Text>
        </View>
      </View>

      {/* Daily Bonus */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Bonus Harian</Text>
        {dailyBonus ? (
          <View>
            <Text style={styles.cardDesc}>
              {dailyBonus.claimed_today || dailyBonus.claimedToday || dailyBonus.claimed
                ? 'Bonus hari ini sudah diklaim!'
                : `Klaim bonus harian Anda: +${formatGC(dailyBonus.amount || 0)}`}
            </Text>
            {!(dailyBonus.claimed_today || dailyBonus.claimedToday || dailyBonus.claimed) && (
              <TouchableOpacity
                style={styles.claimButton}
                onPress={handleClaimBonus}
                disabled={claiming}
              >
                {claiming ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : (
                  <Text style={styles.claimButtonText}>Klaim Bonus</Text>
                )}
              </TouchableOpacity>
            )}
          </View>
        ) : (
          <Text style={styles.cardDesc}>Tidak ada bonus tersedia.</Text>
        )}
      </View>

      {/* Quick Actions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Menu</Text>
        <View style={styles.quickGrid}>
          {quickActions.map((action) => (
            <TouchableOpacity
              key={action.key}
              style={styles.quickItem}
              onPress={() => {
                if (action.tab && action.screen) {
                  navigation.navigate(action.tab, { 
                    screen: action.screen,
                    initial: false,
                  });
                } else if (action.tab) {
                  navigation.navigate(action.tab);
                } else {
                  navigation.navigate(action.screen);
                }
              }}
            >
              <View style={styles.quickIcon}>
                <Ionicons name={action.icon} size={28} color="#DAA520" />
              </View>
              <Text style={styles.quickLabel}>{action.label}</Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>



      {/* Featured Products */}
      {featuredProducts.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Produk Unggulan</Text>
          <FlatList
            data={featuredProducts}
            renderItem={renderProductItem}
            keyExtractor={(item) => String(item.id)}
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.productList}
            initialNumToRender={5}
            maxToRenderPerBatch={5}
            windowSize={3}
            removeClippedSubviews={true}
          />
        </View>
      )}

      <View style={{ height: 32 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f5f5f5',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    paddingTop: 16,
  },
  greeting: {
    fontSize: 14,
    color: '#666',
  },
  userName: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#1a1a2e',
  },
  balanceBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  balanceText: {
    color: '#DAA520',
    fontWeight: 'bold',
    marginLeft: 6,
    fontSize: 14,
  },
  card: {
    backgroundColor: '#fff',
    marginHorizontal: 20,
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#1a1a2e',
    marginBottom: 8,
  },
  cardDesc: {
    fontSize: 14,
    color: '#666',
    marginBottom: 12,
  },
  claimButton: {
    backgroundColor: '#DAA520',
    borderRadius: 8,
    padding: 12,
    alignItems: 'center',
  },
  claimButtonText: {
    color: '#fff',
    fontWeight: 'bold',
    fontSize: 14,
  },
  section: {
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#1a1a2e',
    marginHorizontal: 20,
    marginBottom: 12,
  },
  quickGrid: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    paddingHorizontal: 20,
  },
  quickItem: {
    alignItems: 'center',
    width: (width - 80) / 4,
  },
  quickIcon: {
    backgroundColor: '#fff',
    width: 56,
    height: 56,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 6,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  quickLabel: {
    color: '#1a1a2e',
    fontSize: 12,
    fontWeight: '500',
    marginTop: 4,
    includeFontPadding: false,
    textAlign: 'center',
  },
  categoryList: {
    paddingHorizontal: 20,
  },
  categoryChip: {
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    marginRight: 10,
    elevation: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 1,
    borderWidth: 1,
    borderColor: '#eee',
    justifyContent: 'center',
    alignItems: 'center',
    minHeight: 36,
  },
  categoryText: {
    color: '#1a1a2e',
    fontSize: 14,
    includeFontPadding: false,
  },
  productList: {
    paddingHorizontal: 20,
  },
  productCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 12,
    marginRight: 14,
    width: 160,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  productImage: {
    width: '100%',
    height: 100,
    borderRadius: 8,
    marginBottom: 8,
    backgroundColor: '#eee',
  },
  productImagePlaceholder: {
    width: '100%',
    height: 100,
    borderRadius: 8,
    marginBottom: 8,
    backgroundColor: '#eee',
    justifyContent: 'center',
    alignItems: 'center',
  },
  productName: {
    color: '#1a1a2e',
    fontSize: 14,
    fontWeight: '600',
    marginBottom: 4,
    includeFontPadding: false,
  },
  productPrice: {
    color: '#DAA520',
    fontSize: 14,
    fontWeight: 'bold',
    includeFontPadding: false,
  },
  productGC: {
    color: '#4CAF50',
    fontSize: 12,
    marginTop: 4,
    includeFontPadding: false,
  },
});
