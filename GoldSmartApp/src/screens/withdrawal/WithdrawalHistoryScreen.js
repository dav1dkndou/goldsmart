import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  StyleSheet,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as withdrawals from '../../api/withdrawals';
import { formatCurrency, formatGC, formatDate, getStatusColor, getStatusLabel } from '../../utils/helpers';

export default function WithdrawalHistoryScreen({ navigation }) {
  const [items, setItems] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = useCallback(async (pageNum = 1, refresh = false) => {
    try {
      const res = await withdrawals.getWithdrawals(pageNum);
      const data = res.data.data || res.data;
      const list = data.data || data;
      const meta = data.meta || data;

      if (refresh || pageNum === 1) {
        setItems(list);
      } else {
        setItems((prev) => [...prev, ...list]);
      }
      setLastPage(meta.last_page || 1);
      setPage(pageNum);
    } catch (err) {
      Alert.alert('Error', err.response?.data?.message || 'Gagal memuat data withdrawal');
    } finally {
      setLoading(false);
      setLoadingMore(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchData(1);
  }, [fetchData]);

  useEffect(() => {
    const unsubscribe = navigation.addListener('focus', () => {
      fetchData(1, true);
    });
    return unsubscribe;
  }, [navigation, fetchData]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchData(1, true);
  };

  const onEndReached = () => {
    if (!loadingMore && page < lastPage) {
      setLoadingMore(true);
      fetchData(page + 1);
    }
  };

  const renderItem = useCallback(({ item }) => {
    const statusColor = getStatusColor(item.status);
    const statusLabel = getStatusLabel(item.status);
    return (
      <TouchableOpacity
        style={styles.item}
        onPress={() => navigation.navigate('WithdrawalDetail', { id: item.id })}
        activeOpacity={0.7}
      >
        <View style={styles.itemTop}>
          <Text style={styles.itemDate}>{formatDate(item.created_at || item.date)}</Text>
          <View style={[styles.statusBadge, { backgroundColor: statusColor + '20' }]}>
            <Text style={[styles.statusText, { color: statusColor }]}>{statusLabel}</Text>
          </View>
        </View>
        <View style={styles.itemBody}>
          <View>
            <Text style={styles.itemAmount}>{formatGC(item.gc_amount)} GC</Text>
            <Text style={styles.itemRupiah}>{formatCurrency(item.rupiah_amount || item.amount)}</Text>
          </View>
          <Text style={styles.itemBank}>{item.bank_name}</Text>
        </View>
      </TouchableOpacity>
    );
  }, [navigation]);

  const keyExtractor = useCallback((item, index) => item.id?.toString() || index.toString(), []);

  const renderEmpty = () => (
    <View style={styles.empty}>
      <Ionicons name="wallet-outline" size={48} color="#d1d5db" />
      <Text style={styles.emptyText}>Belum ada riwayat withdrawal</Text>
    </View>
  );

  const renderFooter = () => {
    if (!loadingMore) return null;
    return (
      <View style={styles.footer}>
        <ActivityIndicator size="small" color="#f59e0b" />
      </View>
    );
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#f59e0b" />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={items}
        keyExtractor={keyExtractor}
        renderItem={renderItem}
        ListEmptyComponent={renderEmpty}
        ListFooterComponent={renderFooter}
        onEndReached={onEndReached}
        onEndReachedThreshold={0.3}
        refreshing={refreshing}
        onRefresh={onRefresh}
        contentContainerStyle={items.length === 0 ? styles.emptyContainer : undefined}
      />
      <TouchableOpacity
        style={styles.fab}
        onPress={() => navigation.navigate('WithdrawalForm')}
        activeOpacity={0.8}
      >
        <Ionicons name="add" size={28} color="#fff" />
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f3f4f6' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  item: {
    backgroundColor: '#fff',
    marginHorizontal: 16,
    marginTop: 12,
    borderRadius: 10,
    padding: 14,
    elevation: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 1,
  },
  itemTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  itemDate: { fontSize: 12, color: '#9ca3af' },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 12,
  },
  statusText: { fontSize: 11, fontWeight: '700' },
  itemBody: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
  },
  itemAmount: { fontSize: 16, fontWeight: '700', color: '#1f2937' },
  itemRupiah: { fontSize: 13, color: '#6b7280', marginTop: 2 },
  itemBank: { fontSize: 13, color: '#6b7280', fontWeight: '500' },
  empty: { alignItems: 'center', marginTop: 60 },
  emptyText: { fontSize: 15, color: '#9ca3af', marginTop: 12 },
  emptyContainer: { flexGrow: 1, justifyContent: 'center' },
  footer: { paddingVertical: 16, alignItems: 'center' },
  fab: {
    position: 'absolute',
    right: 20,
    bottom: 24,
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: '#f59e0b',
    justifyContent: 'center',
    alignItems: 'center',
    elevation: 6,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.25,
    shadowRadius: 4,
  },
});
