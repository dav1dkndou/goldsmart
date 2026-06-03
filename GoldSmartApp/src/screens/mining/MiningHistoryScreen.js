import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  ActivityIndicator,
  Alert,
  StyleSheet,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as mining from '../../api/mining';
import { formatGC, formatDate } from '../../utils/helpers';

export default function MiningHistoryScreen() {
  const [history, setHistory] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const fetchHistory = useCallback(async (pageNum = 1, refresh = false) => {
    try {
      const res = await mining.getMiningHistory(pageNum);
      const data = res.data.data || res.data;
      const items = data.data || data;
      const meta = data.meta || data;

      if (refresh || pageNum === 1) {
        setHistory(items);
      } else {
        setHistory((prev) => [...prev, ...items]);
      }
      setLastPage(meta.last_page || 1);
      setPage(pageNum);
    } catch (err) {
      Alert.alert('Error', err.response?.data?.message || 'Gagal memuat riwayat mining');
    } finally {
      setLoading(false);
      setLoadingMore(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchHistory(1);
  }, [fetchHistory]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchHistory(1, true);
  };

  const onEndReached = () => {
    if (!loadingMore && page < lastPage) {
      setLoadingMore(true);
      fetchHistory(page + 1);
    }
  };

  const renderItem = ({ item }) => (
    <View style={styles.item}>
      <View style={styles.itemLeft}>
        <Ionicons name="cube-outline" size={20} color="#f59e0b" />
        <View style={styles.itemInfo}>
          <Text style={styles.itemPlan}>{item.plan_name || item.name || 'Mining'}</Text>
          <Text style={styles.itemDate}>{formatDate(item.created_at || item.date)}</Text>
        </View>
      </View>
      <Text style={styles.itemAmount}>+{formatGC(item.amount)} GC</Text>
    </View>
  );

  const renderEmpty = () => (
    <View style={styles.empty}>
      <Ionicons name="document-text-outline" size={48} color="#d1d5db" />
      <Text style={styles.emptyText}>Belum ada riwayat mining</Text>
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
    <FlatList
      style={styles.container}
      data={history}
      keyExtractor={(item, index) => item.id?.toString() || index.toString()}
      renderItem={renderItem}
      ListEmptyComponent={renderEmpty}
      ListFooterComponent={renderFooter}
      onEndReached={onEndReached}
      onEndReachedThreshold={0.3}
      refreshing={refreshing}
      onRefresh={onRefresh}
      contentContainerStyle={history.length === 0 ? styles.emptyContainer : undefined}
    />
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f3f4f6' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  item: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
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
  itemLeft: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  itemInfo: { marginLeft: 10 },
  itemPlan: { fontSize: 15, fontWeight: '600', color: '#1f2937' },
  itemDate: { fontSize: 12, color: '#9ca3af', marginTop: 2 },
  itemAmount: { fontSize: 16, fontWeight: '700', color: '#10b981' },
  empty: { alignItems: 'center', marginTop: 60 },
  emptyText: { fontSize: 15, color: '#9ca3af', marginTop: 12 },
  emptyContainer: { flexGrow: 1, justifyContent: 'center' },
  footer: { paddingVertical: 16, alignItems: 'center' },
});
