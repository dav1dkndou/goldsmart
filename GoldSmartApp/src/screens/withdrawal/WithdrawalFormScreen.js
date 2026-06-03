import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  ScrollView,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
  StyleSheet,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as withdrawals from '../../api/withdrawals';
import { useAuthStore } from '../../store/authStore';
import { useConfigStore } from '../../store/configStore';
import { formatCurrency, formatGC } from '../../utils/helpers';

export default function WithdrawalFormScreen({ navigation }) {
  const { user } = useAuthStore();
  const { gc_price, withdrawal_fee, min_withdrawal } = useConfigStore();

  const [gcAmount, setGcAmount] = useState('');
  const [bankName, setBankName] = useState('');
  const [accountNumber, setAccountNumber] = useState('');
  const [accountHolder, setAccountHolder] = useState('');
  const [submitting, setSubmitting] = useState(false);

  if (user?.role !== 'member') {
    return (
      <View style={styles.center}>
        <Ionicons name="lock-closed-outline" size={48} color="#9ca3af" />
        <Text style={styles.upgradeText}>
          Upgrade ke Member untuk melakukan withdrawal
        </Text>
        <TouchableOpacity
          style={styles.upgradeButton}
          onPress={() => navigation.navigate('ProfileTab', { screen: 'Membership' })}
        >
          <Text style={styles.upgradeButtonText}>Upgrade Sekarang</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const numericGC = parseFloat(gcAmount) || 0;
  const totalRupiah = numericGC * (gc_price || 0);
  const feeAmount = withdrawal_fee || 0;
  const amountReceived = Math.max(totalRupiah - feeAmount, 0);

  const handleSubmit = async () => {
    if (!gcAmount || !bankName.trim() || !accountNumber.trim() || !accountHolder.trim()) {
      Alert.alert('Error', 'Semua field wajib diisi');
      return;
    }
    if (numericGC < (min_withdrawal || 0)) {
      Alert.alert('Error', `Minimal withdrawal adalah ${formatGC(min_withdrawal)} GC`);
      return;
    }
    if (numericGC > (user?.gc_balance || 0)) {
      Alert.alert('Error', 'Saldo GC tidak mencukupi');
      return;
    }

    Alert.alert(
      'Konfirmasi Withdrawal',
      `Ajukan withdrawal ${formatGC(numericGC)} GC?\nAnda akan menerima ${formatCurrency(amountReceived)}`,
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Ajukan',
          onPress: async () => {
            setSubmitting(true);
            try {
              await withdrawals.createWithdrawal(
                numericGC,
                bankName.trim(),
                accountNumber.trim(),
                accountHolder.trim()
              );
              Alert.alert('Berhasil', 'Pengajuan withdrawal berhasil dikirim');
              useAuthStore.getState().fetchUser();
              navigation.navigate('WithdrawalHistory');
            } catch (err) {
              Alert.alert('Error', err.response?.data?.message || 'Gagal mengajukan withdrawal');
            } finally {
              setSubmitting(false);
            }
          },
        },
      ]
    );
  };

  return (
    <ScrollView style={styles.container} keyboardShouldPersistTaps="handled">
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Form Withdrawal</Text>

        <Text style={styles.label}>Jumlah GC</Text>
        <TextInput
          style={styles.input}
          placeholder={`Min. ${min_withdrawal || 0} GC`}
          keyboardType="numeric"
          value={gcAmount}
          onChangeText={setGcAmount}
        />

        <Text style={styles.label}>Nama Bank</Text>
        <TextInput
          style={styles.input}
          placeholder="Contoh: BCA, BRI, Mandiri"
          value={bankName}
          onChangeText={setBankName}
        />

        <Text style={styles.label}>Nomor Rekening</Text>
        <TextInput
          style={styles.input}
          placeholder="Masukkan nomor rekening"
          keyboardType="numeric"
          value={accountNumber}
          onChangeText={setAccountNumber}
        />

        <Text style={styles.label}>Nama Pemilik Rekening</Text>
        <TextInput
          style={styles.input}
          placeholder="Sesuai buku rekening"
          value={accountHolder}
          onChangeText={setAccountHolder}
        />
      </View>

      {/* Preview Calculation */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Rincian Perhitungan</Text>
        <View style={styles.calcRow}>
          <Text style={styles.calcLabel}>Jumlah GC</Text>
          <Text style={styles.calcValue}>{formatGC(numericGC)} GC</Text>
        </View>
        <View style={styles.calcRow}>
          <Text style={styles.calcLabel}>Harga GC</Text>
          <Text style={styles.calcValue}>{formatCurrency(gc_price || 0)}</Text>
        </View>
        <View style={styles.calcRow}>
          <Text style={styles.calcLabel}>Total Rupiah</Text>
          <Text style={styles.calcValue}>{formatCurrency(totalRupiah)}</Text>
        </View>
        <View style={styles.calcRow}>
          <Text style={styles.calcLabel}>Biaya Admin</Text>
          <Text style={[styles.calcValue, { color: '#ef4444' }]}>
            -{formatCurrency(feeAmount)}
          </Text>
        </View>
        <View style={styles.divider} />
        <View style={styles.calcRow}>
          <Text style={[styles.calcLabel, { fontWeight: '700' }]}>Yang Diterima</Text>
          <Text style={[styles.calcValue, { fontWeight: '700', color: '#10b981' }]}>
            {formatCurrency(amountReceived)}
          </Text>
        </View>
      </View>

      <TouchableOpacity
        style={[styles.submitButton, submitting && { opacity: 0.7 }]}
        onPress={handleSubmit}
        disabled={submitting}
      >
        {submitting ? (
          <ActivityIndicator color="#fff" size="small" />
        ) : (
          <Text style={styles.submitButtonText}>Ajukan Withdrawal</Text>
        )}
      </TouchableOpacity>

      <View style={{ height: 30 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f3f4f6' },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f3f4f6',
    padding: 32,
  },
  upgradeText: {
    fontSize: 16,
    color: '#6b7280',
    textAlign: 'center',
    marginTop: 16,
    marginBottom: 20,
  },
  upgradeButton: {
    backgroundColor: '#f59e0b',
    borderRadius: 8,
    paddingVertical: 12,
    paddingHorizontal: 32,
  },
  upgradeButtonText: { color: '#fff', fontWeight: '700', fontSize: 16 },
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
  cardTitle: { fontSize: 18, fontWeight: '700', color: '#1f2937', marginBottom: 16 },
  label: { fontSize: 14, fontWeight: '600', color: '#374151', marginBottom: 6, marginTop: 12 },
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 15,
    backgroundColor: '#f9fafb',
  },
  calcRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 6,
  },
  calcLabel: { fontSize: 14, color: '#6b7280' },
  calcValue: { fontSize: 14, color: '#1f2937', fontWeight: '500' },
  divider: {
    height: 1,
    backgroundColor: '#e5e7eb',
    marginVertical: 8,
  },
  submitButton: {
    backgroundColor: '#f59e0b',
    margin: 16,
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  submitButtonText: { color: '#fff', fontWeight: '700', fontSize: 16 },
});
