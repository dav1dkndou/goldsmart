import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, FlatList, TextInput, TouchableOpacity,
  Image, StyleSheet, ActivityIndicator, ScrollView, Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import * as videos from '../../api/videos';
import useAuthStore from '../../store/authStore';
import { formatDate, getFullUrl } from '../../utils/helpers';

export default function VideoListScreen({ navigation }) {
  const user = useAuthStore((s) => s.user);
  const [videoList, setVideoList] = useState([]);
  const [categories, setCategories] = useState([]);
  const [selectedCategory, setSelectedCategory] = useState(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const fetchCategories = async () => {
    try {
      const res = await videos.getVideoCategories();
      setCategories(res.data.data || res.data || []);
    } catch (e) {}
  };

  const fetchVideos = async (p = 1, cat = selectedCategory, q = search, reset = false) => {
    if (loading) return;
    setLoading(true);
    try {
      const res = await videos.getVideos(p, cat, q);
      const data = res.data.data || res.data;
      const items = Array.isArray(data) ? data : data.data || [];
      const meta = data.last_page || data.meta?.last_page || 1;
      setVideoList(reset ? items : [...videoList, ...items]);
      setPage(p);
      setLastPage(meta);
    } catch (e) {
      Alert.alert('Error', 'Gagal memuat video');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchCategories();
      fetchVideos(1, selectedCategory, search, true);
    }, [])
  );

  const handleRefresh = () => {
    setRefreshing(true);
    fetchVideos(1, selectedCategory, search, true);
  };

  const handleLoadMore = () => {
    if (page < lastPage && !loading) {
      fetchVideos(page + 1, selectedCategory, search);
    }
  };

  const handleCategorySelect = (catId) => {
    const newCat = catId === selectedCategory ? null : catId;
    setSelectedCategory(newCat);
    fetchVideos(1, newCat, search, true);
  };

  const handleSearch = () => {
    fetchVideos(1, selectedCategory, search, true);
  };

  const renderVideo = useCallback(({ item }) => (
    <VideoItem item={item} onPress={() => navigation.navigate('VideoDetail', { id: item.id })} />
  ), [navigation]);

  const keyExtractor = useCallback((item) => item.id.toString(), []);

  return (
    <View style={styles.container}>
      <View style={styles.searchRow}>
        <TextInput
          style={styles.searchInput}
          placeholder="Cari video..."
          placeholderTextColor="#999"
          value={search}
          onChangeText={setSearch}
          onSubmitEditing={handleSearch}
          returnKeyType="search"
        />
        <TouchableOpacity style={styles.searchBtn} onPress={handleSearch}>
          <Ionicons name="search" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipRow} contentContainerStyle={styles.chipContent}>
        <TouchableOpacity
          style={[styles.chip, !selectedCategory && styles.chipActive]}
          onPress={() => handleCategorySelect(null)}
        >
          <Text style={[styles.chipText, !selectedCategory && styles.chipTextActive]}>
            Semua
          </Text>
        </TouchableOpacity>
        {categories.map((cat) => (
          <TouchableOpacity
            key={cat.id}
            style={[styles.chip, selectedCategory === cat.slug && styles.chipActive]}
            onPress={() => handleCategorySelect(cat.slug)}
          >
            <Text style={[styles.chipText, selectedCategory === cat.slug && styles.chipTextActive]}>
              {cat.name}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      <FlatList
        data={videoList}
        keyExtractor={keyExtractor}
        renderItem={renderVideo}
        onEndReached={handleLoadMore}
        onEndReachedThreshold={0.3}
        refreshing={refreshing}
        onRefresh={handleRefresh}
        initialNumToRender={8}
        maxToRenderPerBatch={8}
        windowSize={5}
        removeClippedSubviews={true}
        ListEmptyComponent={
          !loading && <Text style={styles.emptyText}>Tidak ada video</Text>
        }
        ListFooterComponent={loading && <ActivityIndicator size="small" color="#DAA520" style={{ margin: 16 }} />}
      />

      {user?.role === 'member' && (
        <TouchableOpacity
          style={styles.fab}
          onPress={() => navigation.navigate('UploadVideo')}
        >
          <Ionicons name="add" size={28} color="#fff" />
        </TouchableOpacity>
      )}
    </View>
  );
}

const VideoItem = React.memo(({ item, onPress }) => {
  const [imgError, setImgError] = useState(false);
  const imageUrl = item.thumbnail_url && item.thumbnail_url.trim() !== '' ? getFullUrl(item.thumbnail_url) : null;

  return (
    <TouchableOpacity style={styles.videoCard} onPress={onPress}>
      {imageUrl && !imgError ? (
        <Image
          source={{ uri: imageUrl }}
          style={styles.thumbnail}
          onError={() => setImgError(true)}
        />
      ) : (
        <View style={[styles.thumbnail, styles.placeholderThumb]}>
          <Ionicons name="videocam-outline" size={32} color="#999" />
        </View>
      )}
      <View style={styles.videoInfo}>
        <Text style={styles.videoTitle} numberOfLines={2}>{item.title}</Text>
        <Text style={styles.videoMeta}>{item.user?.name || item.uploader_name || 'Unknown'}</Text>
        <View style={styles.statsRow}>
          <Ionicons name="eye-outline" size={14} color="#999" />
          <Text style={styles.statText}>{item.views_count || 0}</Text>
          <Ionicons name="heart-outline" size={14} color="#999" style={{ marginLeft: 12 }} />
          <Text style={styles.statText}>{item.likes_count || 0}</Text>
        </View>
        <Text style={styles.dateText}>{formatDate(item.created_at)}</Text>
      </View>
    </TouchableOpacity>
  );
});

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  searchRow: { flexDirection: 'row', padding: 12, gap: 8 },
  searchInput: {
    flex: 1, backgroundColor: '#fff', borderRadius: 8,
    paddingHorizontal: 12, paddingVertical: 8, color: '#1a1a2e', fontSize: 14,
    borderWidth: 1, borderColor: '#eee'
  },
  searchBtn: {
    backgroundColor: '#DAA520', borderRadius: 8,
    paddingHorizontal: 14, justifyContent: 'center',
  },
  chipRow: { paddingHorizontal: 12, marginBottom: 8, minHeight: 45, flexGrow: 0 },
  chipContent: { alignItems: 'center' },
  chip: {
    backgroundColor: '#fff', borderRadius: 16,
    paddingHorizontal: 14, paddingVertical: 8, marginRight: 8, alignSelf: 'center',
    borderWidth: 1, borderColor: '#eee',
    justifyContent: 'center', alignItems: 'center', minHeight: 36,
  },
  chipActive: { backgroundColor: '#DAA520', borderColor: '#DAA520' },
  chipText: { color: '#666', fontSize: 14, includeFontPadding: false },
  chipTextActive: { color: '#fff', fontWeight: 'bold' },
  videoCard: {
    flexDirection: 'row', padding: 12, backgroundColor: '#fff',
    borderBottomWidth: 1, borderBottomColor: '#eee',
  },
  thumbnail: { width: 120, height: 80, borderRadius: 8, backgroundColor: '#eee' },
  placeholderThumb: { justifyContent: 'center', alignItems: 'center' },
  videoInfo: { flex: 1, marginLeft: 12 },
  videoTitle: { color: '#1a1a2e', fontSize: 14, fontWeight: '600' },
  videoMeta: { color: '#666', fontSize: 12, marginTop: 2 },
  statsRow: { flexDirection: 'row', alignItems: 'center', marginTop: 4 },
  statText: { color: '#888', fontSize: 12, marginLeft: 4 },
  dateText: { color: '#888', fontSize: 11, marginTop: 2 },
  emptyText: { color: '#999', textAlign: 'center', marginTop: 40 },
  fab: {
    position: 'absolute', bottom: 20, right: 20,
    backgroundColor: '#DAA520', width: 56, height: 56, borderRadius: 28,
    justifyContent: 'center', alignItems: 'center', elevation: 4,
  },
});
