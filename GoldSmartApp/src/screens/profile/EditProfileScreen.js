import React, { useState, useEffect } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, Image,
  StyleSheet, Alert, ActivityIndicator, ScrollView,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import * as users from '../../api/users';
import useAuthStore from '../../store/authStore';

export default function EditProfileScreen({ navigation }) {
  const user = useAuthStore((s) => s.user);
  const fetchUser = useAuthStore((s) => s.fetchUser);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [avatarUri, setAvatarUri] = useState(null);
  const [saving, setSaving] = useState(false);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);

  useEffect(() => {
    if (user) {
      setName(user.name || '');
      setPhone(user.phone || '');
      if (user.avatar) {
        setAvatarUri(`https://goldsmart.online/uploads/avatars/${user.avatar}`);
      }
    }
  }, [user]);

  const handlePickAvatar = async () => {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.7,
      base64: true,
    });

    if (!result.canceled && result.assets?.[0]) {
      setUploadingAvatar(true);
      try {
        await users.uploadAvatar(result.assets[0].base64);
        setAvatarUri(result.assets[0].uri);
        await fetchUser();
        Alert.alert('Berhasil', 'Foto profil berhasil diperbarui');
      } catch (e) {
        Alert.alert('Error', 'Gagal mengupload foto');
      } finally {
        setUploadingAvatar(false);
      }
    }
  };

  const handleSave = async () => {
    if (!name.trim()) return Alert.alert('Error', 'Nama harus diisi');
    setSaving(true);
    try {
      await users.updateProfile(name.trim(), phone.trim());
      await fetchUser();
      Alert.alert('Berhasil', 'Profil berhasil diperbarui', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal menyimpan profil';
      Alert.alert('Error', msg);
    } finally {
      setSaving(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.avatarSection}>
        {avatarUri ? (
          <Image source={{ uri: avatarUri }} style={styles.avatar} />
        ) : (
          <View style={[styles.avatar, styles.avatarPlaceholder]}>
            <Ionicons name="person" size={40} color="#999" />
          </View>
        )}
        <TouchableOpacity style={styles.changePhotoBtn} onPress={handlePickAvatar} disabled={uploadingAvatar}>
          {uploadingAvatar ? (
            <ActivityIndicator size="small" color="#DAA520" />
          ) : (
            <Text style={styles.changePhotoText}>Ganti Foto</Text>
          )}
        </TouchableOpacity>
      </View>

      <Text style={styles.label}>Nama</Text>
      <TextInput
        style={styles.input}
        value={name}
        onChangeText={setName}
        placeholder="Nama lengkap"
        placeholderTextColor="#999"
      />

      <Text style={styles.label}>No. Telepon</Text>
      <TextInput
        style={styles.input}
        value={phone}
        onChangeText={setPhone}
        placeholder="08xxxxxxxxxx"
        placeholderTextColor="#999"
        keyboardType="phone-pad"
      />

      <TouchableOpacity
        style={[styles.saveBtn, saving && { opacity: 0.6 }]}
        onPress={handleSave}
        disabled={saving}
      >
        {saving ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.saveBtnText}>Simpan</Text>
        )}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16 },
  avatarSection: { alignItems: 'center', marginBottom: 20 },
  avatar: { width: 100, height: 100, borderRadius: 50, backgroundColor: '#eee' },
  avatarPlaceholder: { justifyContent: 'center', alignItems: 'center' },
  changePhotoBtn: { marginTop: 8, paddingVertical: 6 },
  changePhotoText: { color: '#DAA520', fontSize: 14, fontWeight: '600' },
  label: { color: '#DAA520', fontSize: 14, fontWeight: '600', marginTop: 16, marginBottom: 6 },
  input: {
    backgroundColor: '#fff', borderRadius: 8,
    paddingHorizontal: 14, paddingVertical: 10, color: '#1a1a2e', fontSize: 14,
    borderWidth: 1, borderColor: '#eee',
  },
  saveBtn: {
    backgroundColor: '#DAA520', borderRadius: 8,
    paddingVertical: 14, alignItems: 'center', marginTop: 24,
  },
  saveBtnText: { color: '#fff', fontSize: 16, fontWeight: 'bold' },
});
