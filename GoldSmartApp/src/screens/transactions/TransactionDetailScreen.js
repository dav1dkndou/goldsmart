import React, { useState, useEffect, useRef } from 'react';
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
  Modal,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import * as Sharing from 'expo-sharing';
import ViewShot from 'react-native-view-shot';
import { getTransaction, uploadPaymentProof } from '../../api/transactions';
import { formatCurrency, formatGC, formatDate, getStatusColor, getStatusLabel } from '../../utils/helpers';

const { width } = Dimensions.get('window');

export default function TransactionDetailScreen({ route }) {
  const { id } = route.params;
  const [transaction, setTransaction] = useState(null);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [showReceipt, setShowReceipt] = useState(false);
  const viewShotRef = useRef(null);

  useEffect(() => {
    fetchTransaction();
  }, [id]);

  const fetchTransaction = async () => {
    setLoading(true);
    try {
      const res = await getTransaction(id);
      setTransaction(res.data?.data || res.data);
    } catch (e) {
      Alert.alert('Error', 'Gagal memuat detail transaksi');
    } finally {
      setLoading(false);
    }
  };

  const handleUploadProof = async () => {
    try {
      const permResult = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!permResult.granted) {
        Alert.alert('Izin Diperlukan', 'Akses galeri diperlukan untuk mengunggah bukti pembayaran.');
        return;
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions?.Images || ['images'],
        quality: 0.8,
        base64: true,
      });

      if (result.canceled) return;

      const asset = result.assets?.[0];
      if (!asset?.base64) {
        Alert.alert('Error', 'Gagal membaca gambar');
        return;
      }

      setUploading(true);
      await uploadPaymentProof(id, asset.base64);
      Alert.alert('Berhasil', 'Bukti pembayaran berhasil diunggah');
      await fetchTransaction();
    } catch (e) {
      Alert.alert('Error', e.response?.data?.message || 'Gagal mengunggah bukti pembayaran');
    } finally {
      setUploading(false);
    }
  };

  const handleDownloadReceipt = async () => {
    try {
      if (viewShotRef.current) {
        const uri = await viewShotRef.current.capture();
        if (await Sharing.isAvailableAsync()) {
          await Sharing.shareAsync(uri, { mimeType: 'image/png', dialogTitle: 'Struk Transaksi' });
        } else {
          Alert.alert('Berhasil', 'Struk tersimpan di: ' + uri);
        }
      }
    } catch (e) {
      Alert.alert('Error', 'Gagal memproses struk.');
    }
  };

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#D4A843" />
      </View>
    );
  }

  if (!transaction) {
    return (
      <View style={styles.centerContainer}>
        <Text style={styles.errorText}>Transaksi tidak ditemukan</Text>
      </View>
    );
  }

  const statusColor = getStatusColor(transaction.status);
  const statusLabel = getStatusLabel(transaction.status);
  const items = transaction.items || transaction.details || [transaction];
  const isPaid = ['completed', 'approved', 'success'].includes(transaction.status?.toLowerCase());

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.headerCard}>
        {isPaid && (
          <View style={styles.stampContainer}>
            <Text style={styles.stampText}>LUNAS</Text>
          </View>
        )}
        <View style={styles.headerRow}>
          <Text style={styles.orderNumber}>#{transaction.order_number || transaction.id}</Text>
          <View style={[styles.statusBadge, { backgroundColor: statusColor + '20' }]}>
            <Text style={[styles.statusText, { color: statusColor }]}>{statusLabel}</Text>
          </View>
        </View>
        <Text style={styles.date}>{formatDate(transaction.created_at)}</Text>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Item Pesanan</Text>
        {items.map((item, index) => {
          const name = item.product?.name || item.product_name || item.name || 'Produk';
          const price = item.price || item.product?.price || 0;
          const qty = item.quantity || item.qty || 0;
          const gcBonus = item.gc_bonus || item.product?.gc_bonus || 0;

          return (
            <View key={index} style={styles.itemRow}>
              <View style={styles.itemLeft}>
                <Text style={styles.itemName}>{name}</Text>
                <Text style={styles.itemQty}>x{qty} @ {formatCurrency(price)}</Text>
                {gcBonus > 0 && (
                  <Text style={styles.itemGC}>+{formatGC(gcBonus * qty)}</Text>
                )}
              </View>
              <Text style={styles.itemTotal}>{formatCurrency(price * qty)}</Text>
            </View>
          );
        })}
      </View>

      <View style={styles.section}>
        <View style={styles.totalRow}>
          <Text style={styles.totalLabel}>Total Harga</Text>
          <Text style={styles.totalPrice}>
            {formatCurrency(transaction.total_price || transaction.total)}
          </Text>
        </View>
        {(transaction.gc_earned > 0 || transaction.total_gc > 0 || transaction.gcReward > 0) && (
          <View style={styles.totalRow}>
            <Text style={styles.totalLabel}>Total GC Earned</Text>
            <Text style={styles.totalGC}>
              +{formatGC(transaction.gc_earned || transaction.total_gc || transaction.gcReward)}
            </Text>
          </View>
        )}
      </View>

      {isPaid && (
        <TouchableOpacity style={styles.receiptButton} onPress={() => setShowReceipt(true)}>
          <Ionicons name="receipt-outline" size={20} color="#fff" />
          <Text style={styles.receiptButtonText}>Lihat Struk Transaksi</Text>
        </TouchableOpacity>
      )}
      {transaction.status === 'pending' && !transaction.payment_proof && (
        <TouchableOpacity
          style={styles.uploadBtn}
          onPress={handleUploadProof}
          disabled={uploading}
        >
          {uploading ? (
            <ActivityIndicator size="small" color="#fff" />
          ) : (
            <>
              <Ionicons name="cloud-upload-outline" size={20} color="#fff" />
              <Text style={styles.uploadBtnText}>Upload Bukti Pembayaran</Text>
            </>
          )}
        </TouchableOpacity>
      )}

      {transaction.payment_proof && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Bukti Pembayaran</Text>
          <Image
            source={{ uri: `https://goldsmart.online/uploads/payments/${transaction.payment_proof}` }}
            style={styles.proofImage}
            resizeMode="contain"
          />
          {transaction.status === 'pending' && (
            <TouchableOpacity
              style={[styles.uploadBtn, styles.reuploadBtn]}
              onPress={handleUploadProof}
              disabled={uploading}
            >
              {uploading ? (
                <ActivityIndicator size="small" color="#D4A843" />
              ) : (
                <>
                  <Ionicons name="refresh" size={18} color="#D4A843" />
                  <Text style={[styles.uploadBtnText, styles.reuploadBtnText]}>Upload Ulang</Text>
                </>
              )}
            </TouchableOpacity>
          )}
        </View>
      )}

      <Modal
        visible={showReceipt}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setShowReceipt(false)}
      >
        <View style={styles.modalContainer}>
          <ViewShot ref={viewShotRef} options={{ format: 'png', quality: 0.9 }} style={styles.receiptCard}>
            <View style={styles.receiptHeader}>
              <Text style={styles.receiptTitle}>GOLDSMART NUSANTARA</Text>
              <Text style={styles.receiptDate}>{formatDate(transaction.created_at)}</Text>
            </View>

            <View style={styles.receiptRow}>
              <Text style={styles.receiptLabel}>No. Transaksi:</Text>
              <Text style={styles.receiptValue}>#{transaction.order_number || transaction.id}</Text>
            </View>
            <View style={styles.receiptRow}>
              <Text style={styles.receiptLabel}>Status:</Text>
              <Text style={[styles.receiptValue, { color: '#10B981' }]}>LUNAS</Text>
            </View>

            <View style={styles.receiptDivider} />

            {items.map((item, index) => {
              const name = item.product?.name || item.product_name || item.name || 'Produk';
              const price = item.price || item.product?.price || 0;
              const qty = item.quantity || item.qty || 0;
              return (
                <View key={index} style={{ marginBottom: 8 }}>
                  <Text style={styles.receiptValue}>{name}</Text>
                  <View style={styles.receiptRow}>
                    <Text style={styles.receiptLabel}>{qty} x {formatCurrency(price)}</Text>
                    <Text style={styles.receiptValue}>{formatCurrency(price * qty)}</Text>
                  </View>
                </View>
              );
            })}

            <View style={styles.receiptDivider} />

            <View style={styles.receiptRow}>
              <Text style={styles.receiptLabel}>Total Bayar:</Text>
              <Text style={[styles.receiptValue, { fontSize: 18, color: '#D4A843' }]}>
                {formatCurrency(transaction.total_price || transaction.total)}
              </Text>
            </View>

            <View style={styles.receiptFooter}>
              <Text style={styles.receiptFooterText}>Terima kasih telah berbelanja!</Text>
            </View>
          </ViewShot>

          <View style={styles.modalActionRow}>
            <TouchableOpacity style={styles.downloadReceiptBtn} onPress={handleDownloadReceipt}>
              <Ionicons name="share-social-outline" size={20} color="#fff" />
              <Text style={styles.closeReceiptText}>Bagikan</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.closeReceiptBtn} onPress={() => setShowReceipt(false)}>
              <Text style={styles.closeReceiptText}>Tutup</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16, paddingBottom: 32 },
  centerContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  errorText: { fontSize: 16, color: '#999' },
  headerCard: {
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 16,
    marginBottom: 12,
    elevation: 1,
    position: 'relative',
    overflow: 'hidden',
  },
  stampContainer: {
    position: 'absolute',
    top: 20,
    right: 20,
    borderWidth: 3,
    borderColor: '#10B981',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 4,
    transform: [{ rotate: '-15deg' }],
    bottom: -15,
    right: 20,
  },
  stampText: {
    color: '#10B981',
    fontSize: 24,
    fontWeight: '900',
    letterSpacing: 2,
    textTransform: 'uppercase',
  },
  receiptButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#1a1a2e',
    padding: 14,
    borderRadius: 8,
    marginVertical: 16,
    gap: 8,
  },
  receiptButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  modalContainer: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  receiptCard: {
    width: '100%',
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 20,
    elevation: 10,
  },
  receiptHeader: {
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: '#ddd',
    borderStyle: 'dashed',
    paddingBottom: 16,
    marginBottom: 16,
  },
  receiptTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 4,
  },
  receiptDate: {
    fontSize: 12,
    color: '#666',
  },
  receiptRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  receiptLabel: {
    fontSize: 14,
    color: '#666',
  },
  receiptValue: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#333',
  },
  receiptDivider: {
    height: 1,
    backgroundColor: '#ddd',
    borderStyle: 'dashed',
    marginVertical: 12,
  },
  receiptFooter: {
    alignItems: 'center',
    marginTop: 20,
  },
  receiptFooterText: {
    fontSize: 12,
    color: '#888',
    fontStyle: 'italic',
  },
  modalActionRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    width: '100%',
    marginTop: 20,
    gap: 12,
  },
  downloadReceiptBtn: {
    flex: 1,
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#DAA520',
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
  },
  closeReceiptBtn: {
    flex: 1,
    backgroundColor: '#1a1a2e',
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
  },
  closeReceiptText: {
    color: '#fff',
    fontWeight: 'bold',
    fontSize: 16,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  orderNumber: { fontSize: 18, fontWeight: '700', color: '#333' },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: { fontSize: 13, fontWeight: '600' },
  date: { fontSize: 14, color: '#999' },
  section: {
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 16,
    marginBottom: 12,
    elevation: 1,
  },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: '#333', marginBottom: 12 },
  itemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  itemLeft: { flex: 1, marginRight: 12 },
  itemName: { fontSize: 14, fontWeight: '500', color: '#333' },
  itemQty: { fontSize: 13, color: '#666', marginTop: 2 },
  itemGC: { fontSize: 12, color: '#856404', marginTop: 2 },
  itemTotal: { fontSize: 14, fontWeight: '600', color: '#333' },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  totalLabel: { fontSize: 15, color: '#666' },
  totalPrice: { fontSize: 18, fontWeight: '700', color: '#D4A843' },
  totalGC: { fontSize: 15, fontWeight: '600', color: '#856404' },
  uploadBtn: {
    flexDirection: 'row',
    backgroundColor: '#D4A843',
    paddingVertical: 14,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
    marginBottom: 12,
  },
  uploadBtnText: { color: '#fff', fontSize: 15, fontWeight: '600' },
  reuploadBtn: {
    backgroundColor: 'transparent',
    borderWidth: 1,
    borderColor: '#D4A843',
    marginTop: 12,
    marginBottom: 0,
  },
  reuploadBtnText: { color: '#D4A843' },
  proofImage: {
    width: '100%',
    height: width * 0.8,
    borderRadius: 8,
    backgroundColor: '#f0f0f0',
  },
});
