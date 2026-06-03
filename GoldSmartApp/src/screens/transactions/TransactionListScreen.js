import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { getTransactions } from '../../api/transactions';
import { formatCurrency, formatGC, formatDate, getStatusColor, getStatusLabel } from '../../utils/helpers';

const STATUS_TABS = [
  { key: null, label: 'Semua' },
  { key: 'pending', label: 'Pending' },
  { key: 'verified', label: 'Verified' },
  { key: 'completed', label: 'Completed' },
  { key: 'cancelled', label: 'Cancelled' },
];

export default function TransactionListScreen({ navigation }) {
  const [transactions, setTransactions] = useState([]);
  const [selectedStatus, setSelectedStatus] = useState(null);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = useCallback(async (pageNum = 1, reset = false) => {
    if (loading) return;
    setLoading(true);
    try {
      const res = await getTransactions(pageNum, selectedStatus);
      const data = res.data?.data?.data || res.data?.data || res.data || [];
      const lastPage = res.data?.data?.last_page || res.data?.last_page || 1;
      if (reset) {
        setTransactions(data);
      } else {
        setTransactions(prev => [...prev, ...data]);
      }
      setHasMore(pageNum < lastPage);
      setPage(pageNum);
    } catch (e) {
      Alert.alert('Error', 'Gagal memuat transaksi');
    } finally {
      setLoading(false);
    }
  }, [selectedStatus]);

  useEffect(() => {
    fetchData(1, true);
  }, [selectedStatus]);

  const handleRefresh = async () => {
    setRefreshing(true);
    await fetchData(1, true);
    setRefreshing(false);
  };

  const handleEndReached = () => {
    if (hasMore && !loading) {
      fetchData(page + 1, false);
    }
  };

  const renderTransaction = useCallback(({ item }) => {
    const statusColor = getStatusColor(item.status);
    const statusLabel = getStatusLabel(item.status);

    return (
      <TouchableOpacity
        style={styles.txCard}
        onPress={() => navigation.navigate('TransactionDetail', { id: item.id })}
      >
        <View style={styles.txHeader}>
          <Text style={styles.txOrderNo}>#{item.order_number || item.id}</Text>
          <View style={[styles.statusBadge, { backgroundColor: statusColor + '20' }]}>
            <Text style={[styles.statusText, { color: statusColor }]}>{statusLabel}</Text>
          </View>
        </View>

        <Text style={styles.txDate}>{formatDate(item.created_at)}</Text>

        <View style={styles.txFooter}>
          <Text style={styles.txPrice}>{formatCurrency(item.total_price || item.total)}</Text>
          {(item.gc_earned > 0 || item.total_gc > 0) && (
            <Text style={styles.txGC}>+{formatGC(item.gc_earned || item.total_gc)}</Text>
          )}
        </View>
      </TouchableOpacity>
    );
  }, [navigation]);

  const keyExtractor = useCallback((item, index) => item.id ? `${item.id}-${index}` : index.toString(), []);

  return (
    <View style={styles.container}>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        style={styles.tabScroll}
        contentContainerStyle={styles.tabContent}
      >
        {STATUS_TABS.map(tab => (
          <TouchableOpacity
            key={tab.key || 'all'}
            style={[styles.tab, selectedStatus === tab.key && styles.tabActive]}
            onPress={() => setSelectedStatus(tab.key)}
          >
            <Text style={[styles.tabText, selectedStatus === tab.key && styles.tabTextActive]}>
              {tab.label}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      <FlatList
        data={transactions}
        renderItem={renderTransaction}
        keyExtractor={keyExtractor}
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
              <Ionicons name="receipt-outline" size={60} color="#ccc" />
              <Text style={styles.emptyText}>Belum ada transaksi</Text>
            </View>
          )
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  tabScroll: { backgroundColor: '#fff', maxHeight: 50, borderBottomWidth: 1, borderBottomColor: '#eee' },
  tabContent: { paddingHorizontal: 12, alignItems: 'center' },
  tab: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    marginRight: 4,
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  tabActive: { borderBottomColor: '#D4A843' },
  tabText: { fontSize: 14, color: '#666', includeFontPadding: false },
  tabTextActive: { color: '#D4A843', fontWeight: '600' },
  listContent: { padding: 12 },
  txCard: {
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 14,
    marginBottom: 10,
    elevation: 1,
  },
  txHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  txOrderNo: { fontSize: 15, fontWeight: '600', color: '#333' },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: { fontSize: 12, fontWeight: '600' },
  txDate: { fontSize: 13, color: '#999', marginBottom: 8 },
  txFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  txPrice: { fontSize: 16, fontWeight: '700', color: '#D4A843' },
  txGC: { fontSize: 13, fontWeight: '600', color: '#856404' },
  emptyContainer: { alignItems: 'center', paddingTop: 60 },
  emptyText: { fontSize: 15, color: '#999', marginTop: 12 },
});
