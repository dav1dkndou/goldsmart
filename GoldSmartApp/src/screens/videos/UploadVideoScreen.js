import React, { useState, useEffect } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, Image,
  StyleSheet, Alert, ActivityIndicator, ScrollView,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import * as videos from '../../api/videos';

export default function UploadVideoScreen({ navigation }) {
  const [title, setTitle] = useState('');
  const [url, setUrl] = useState('');
  const [categoryId, setCategoryId] = useState(null);
  const [categories, setCategories] = useState([]);
  const [thumbnailBase64, setThumbnailBase64] = useState(null);
  const [thumbnailUri, setThumbnailUri] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const fetchCategories = async () => {
      try {
        const res = await videos.getVideoCategories();
        setCategories(res.data.data || res.data || []);
      } catch (e) {}
    };
    fetchCategories();
  }, []);

  const pickThumbnail = async () => {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [16, 9],
      quality: 0.7,
      base64: true,
    });

    if (!result.canceled && result.assets?.[0]) {
      setThumbnailBase64(result.assets[0].base64);
      setThumbnailUri(result.assets[0].uri);
    }
  };

  const handleUpload = async () => {
    if (!title.trim()) return Alert.alert('Error', 'Judul harus diisi');
    if (!url.trim()) return Alert.alert('Error', 'URL video harus diisi');
    if (!categoryId) return Alert.alert('Error', 'Pilih kategori');

    setLoading(true);
    try {
      await videos.uploadVideo(title.trim(), url.trim(), thumbnailBase64, categoryId);
      Alert.alert('Berhasil', 'Video berhasil diupload', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal mengupload video';
      Alert.alert('Error', msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.label}>Judul</Text>
      <TextInput
        style={styles.input}
        value={title}
        onChangeText={setTitle}
        placeholder="Judul video"
        placeholderTextColor="#999"
      />

      <Text style={styles.label}>URL Video</Text>
      <TextInput
        style={styles.input}
        value={url}
        onChangeText={setUrl}
        placeholder="https://youtube.com/watch?v=..."
        placeholderTextColor="#999"
        autoCapitalize="none"
        keyboardType="url"
      />

      <Text style={styles.label}>Kategori</Text>
      <View style={styles.categoryRow}>
        {categories.map((cat) => (
          <TouchableOpacity
            key={cat.id}
            style={[styles.catBtn, categoryId === cat.id && styles.catBtnActive]}
            onPress={() => setCategoryId(cat.id)}
          >
            <Text style={[styles.catText, categoryId === cat.id && styles.catTextActive]}>
              {cat.name}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <Text style={styles.label}>Thumbnail</Text>
      <TouchableOpacity style={styles.thumbPicker} onPress={pickThumbnail}>
        {thumbnailUri ? (
          <Image source={{ uri: thumbnailUri }} style={styles.thumbPreview} />
        ) : (
          <View style={styles.thumbPlaceholder}>
            <Ionicons name="image-outline" size={32} color="#999" />
            <Text style={styles.thumbText}>Pilih Thumbnail</Text>
          </View>
        )}
      </TouchableOpacity>

      <TouchableOpacity
        style={[styles.uploadBtn, loading && { opacity: 0.6 }]}
        onPress={handleUpload}
        disabled={loading}
      >
        {loading ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.uploadBtnText}>Upload Video</Text>
        )}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#1a1a2e' },
  content: { padding: 16 },
  label: { color: '#DAA520', fontSize: 14, fontWeight: '600', marginTop: 16, marginBottom: 6 },
  input: {
    backgroundColor: '#2a2a4a', borderRadius: 8,
    paddingHorizontal: 14, paddingVertical: 10, color: '#fff', fontSize: 14,
  },
  categoryRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  catBtn: {
    backgroundColor: '#2a2a4a', borderRadius: 16,
    paddingHorizontal: 14, paddingVertical: 6,
  },
  catBtnActive: { backgroundColor: '#DAA520' },
  catText: { color: '#ccc', fontSize: 13 },
  catTextActive: { color: '#fff', fontWeight: 'bold' },
  thumbPicker: { borderRadius: 8, overflow: 'hidden' },
  thumbPreview: { width: '100%', height: 180, borderRadius: 8 },
  thumbPlaceholder: {
    backgroundColor: '#2a2a4a', height: 120, borderRadius: 8,
    justifyContent: 'center', alignItems: 'center',
  },
  thumbText: { color: '#999', marginTop: 4, fontSize: 13 },
  uploadBtn: {
    backgroundColor: '#DAA520', borderRadius: 8,
    paddingVertical: 14, alignItems: 'center', marginTop: 24,
  },
  uploadBtnText: { color: '#fff', fontSize: 16, fontWeight: 'bold' },
});
