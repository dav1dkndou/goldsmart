import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  ActivityIndicator,
  Alert,
  StyleSheet,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as withdrawals from '../../api/withdrawals';
import { formatCurrency, formatGC, formatDate, getStatusColor, getStatusLabel } from '../../utils/helpers';

export default function WithdrawalDetailScreen({ route }) {
  const { id } = route.params;
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchDetail = async () => {
      try {
        const res = await withdrawals.getWithdrawal(id);
        setData(res.data.data || res.data);
      } catch (err) {
        Alert.alert('Error', err.response?.data?.message || 'Gagal memuat detail withdrawal');
      } finally {
        setLoading(false);
      }
    };
    fetchDetail();
  }, [id]);

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#f59e0b" />
      </View>
    );
  }

  if (!data) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorText}>Data tidak ditemukan</Text>
      </View>
    );
  }

  const statusColor = getStatusColor(data.status);
  const statusLabel = getStatusLabel(data.status);
  const statusHistory = data.status_history || data.timeline || [];

  return (
    <ScrollView style={styles.container}>
      {/* Status Badge */}
      <View style={styles.card}>
        <View style={styles.statusCenter}>
          <View style={[styles.statusBadgeLarge, { backgroundColor: statusColor + '20' }]}>
            <Text style={[styles.statusTextLarge, { color: statusColor }]}>{statusLabel}</Text>
          </View>
          <Text style={styles.dateText}>{formatDate(data.created_at || data.date)}</Text>
        </View>
      </View>

      {/* Amount Details */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Detail Jumlah</Text>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>Jumlah GC</Text>
          <Text style={styles.rowValue}>{formatGC(data.gc_amount)} GC</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>Jumlah Rupiah</Text>
          <Text style={styles.rowValue}>{formatCurrency(data.rupiah_amount || data.amount)}</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>Biaya Admin</Text>
          <Text style={[styles.rowValue, { color: '#ef4444' }]}>
            -{formatCurrency(data.admin_fee || data.fee || 0)}
          </Text>
        </View>
        <View style={styles.divider} />
        <View style={styles.row}>
          <Text style={[styles.rowLabel, { fontWeight: '700' }]}>Yang Diterima</Text>
          <Text style={[styles.rowValue, { fontWeight: '700', color: '#10b981' }]}>
            {formatCurrency(data.net_amount || data.amount_received || 0)}
          </Text>
        </View>
      </View>

      {/* Bank Details */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Detail Rekening</Text>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>Nama Bank</Text>
          <Text style={styles.rowValue}>{data.bank_name}</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>Nomor Rekening</Text>
          <Text style={styles.rowValue}>{data.account_number}</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>Nama Pemilik</Text>
          <Text style={styles.rowValue}>{data.account_holder}</Text>
        </View>
      </View>

      {/* Status Timeline */}
      {statusHistory.length > 0 && (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Riwayat Status</Text>
          {statusHistory.map((entry, index) => {
            const entryColor = getStatusColor(entry.status);
            return (
              <View key={index} style={styles.timelineItem}>
                <View style={styles.timelineDot}>
                  <View style={[styles.dot, { backgroundColor: entryColor }]} />
                  {index < statusHistory.length - 1 && <View style={styles.timelineLine} />}
                </View>
                <View style={styles.timelineContent}>
                  <Text style={[styles.timelineStatus, { color: entryColor }]}>
                    {getStatusLabel(entry.status)}
                  </Text>
                  <Text style={styles.timelineDate}>
                    {formatDate(entry.created_at || entry.date)}
                  </Text>
                  {entry.note && <Text style={styles.timelineNote}>{entry.note}</Text>}
                </View>
              </View>
            );
          })}
        </View>
      )}

      <View style={{ height: 30 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f3f4f6' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  errorText: { fontSize: 15, color: '#9ca3af' },
  card: {
    backgroundColor: '#fff',
    margin: 16,
    marginBottom: 0,
    borderRadius: 12,
    padding: 16,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  cardTitle: { fontSize: 16, fontWeight: '700', color: '#1f2937', marginBottom: 12 },
  statusCenter: { alignItems: 'center' },
  statusBadgeLarge: {
    paddingHorizontal: 16,
    paddingVertical: 6,
    borderRadius: 16,
  },
  statusTextLarge: { fontSize: 14, fontWeight: '700' },
  dateText: { fontSize: 13, color: '#9ca3af', marginTop: 8 },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 6,
  },
  rowLabel: { fontSize: 14, color: '#6b7280' },
  rowValue: { fontSize: 14, color: '#1f2937', fontWeight: '500' },
  divider: { height: 1, backgroundColor: '#e5e7eb', marginVertical: 8 },
  timelineItem: { flexDirection: 'row', marginBottom: 4 },
  timelineDot: { alignItems: 'center', width: 24 },
  dot: { width: 10, height: 10, borderRadius: 5, marginTop: 4 },
  timelineLine: {
    width: 2,
    flex: 1,
    backgroundColor: '#e5e7eb',
    marginVertical: 2,
  },
  timelineContent: { flex: 1, marginLeft: 8, paddingBottom: 16 },
  timelineStatus: { fontSize: 14, fontWeight: '600' },
  timelineDate: { fontSize: 12, color: '#9ca3af', marginTop: 2 },
  timelineNote: { fontSize: 13, color: '#6b7280', marginTop: 4 },
});
