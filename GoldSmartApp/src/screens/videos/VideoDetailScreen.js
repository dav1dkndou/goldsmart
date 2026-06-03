import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, FlatList, TextInput, TouchableOpacity,
  StyleSheet, ActivityIndicator, Alert, Linking, ScrollView,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useVideoPlayer, VideoView } from 'expo-video';
import * as videos from '../../api/videos';
import useAuthStore from '../../store/authStore';
import { formatDate, getFullUrl } from '../../utils/helpers';

export default function VideoDetailScreen({ route }) {
  const { id } = route.params;
  const user = useAuthStore((s) => s.user);
  const [video, setVideo] = useState(null);
  const [comments, setComments] = useState([]);
  const [newComment, setNewComment] = useState('');
  const [liked, setLiked] = useState(false);
  const [likesCount, setLikesCount] = useState(0);
  const [loading, setLoading] = useState(true);

  const [submitting, setSubmitting] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const videoSource = video?.video_url ? getFullUrl(video.video_url) : null;
  const player = useVideoPlayer(videoSource, player => {
    player.loop = false;
  });

  const fetchVideo = async () => {
    try {
      const res = await videos.getVideo(id);
      const data = res.data.data || res.data;
      setVideo(data);
      setLiked(data.is_liked || false);
      setLikesCount(data.likes_count || 0);
    } catch (e) {
      Alert.alert('Error', 'Gagal memuat video');
    }
  };

  const fetchComments = async () => {
    try {
      const res = await videos.getComments(id);
      const data = res.data.data || res.data;
      setComments(Array.isArray(data) ? data : data.data || []);
    } catch (e) {}
  };

  const loadAll = async () => {
    setLoading(true);
    await Promise.all([fetchVideo(), fetchComments()]);
    setLoading(false);
  };

  useEffect(() => {
    loadAll();
    videos.recordView(id).catch(() => {});
  }, []);

  const handleRefresh = async () => {
    setRefreshing(true);
    await Promise.all([fetchVideo(), fetchComments()]);
    setRefreshing(false);
  };

  const handleLike = async () => {
    try {
      await videos.toggleLike(id);
      setLiked(!liked);
      setLikesCount(liked ? likesCount - 1 : likesCount + 1);
    } catch (e) {
      Alert.alert('Error', 'Gagal menyukai video');
    }
  };

  const handleAddComment = async () => {
    if (!newComment.trim()) return;
    setSubmitting(true);
    try {
      await videos.addComment(id, newComment.trim());
      setNewComment('');
      await fetchComments();
    } catch (e) {
      Alert.alert('Error', 'Gagal menambah komentar');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDeleteComment = (commentId) => {
    Alert.alert('Hapus Komentar', 'Yakin ingin menghapus komentar ini?', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus',
        style: 'destructive',
        onPress: async () => {
          try {
            await videos.deleteComment(commentId);
            await fetchComments();
          } catch (e) {
            Alert.alert('Error', 'Gagal menghapus komentar');
          }
        },
      },
    ]);
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#DAA520" />
      </View>
    );
  }

  const renderComment = ({ item }) => (
    <View style={styles.commentItem}>
      <View style={styles.commentHeader}>
        <Text style={styles.commentUser}>{item.user?.name || 'User'}</Text>
        <Text style={styles.commentDate}>{formatDate(item.created_at)}</Text>
      </View>
      <Text style={styles.commentContent}>{item.content || item.comment}</Text>
      {(item.user_id === user?.id || item.user?.id === user?.id) && (
        <TouchableOpacity onPress={() => handleDeleteComment(item.id)} style={styles.deleteBtn}>
          <Ionicons name="trash-outline" size={16} color="#EF4444" />
        </TouchableOpacity>
      )}
    </View>
  );

  const renderHeader = () => (
    <View>
      {videoSource ? (
        <VideoView
          style={styles.videoPlayer}
          player={player}
          allowsFullscreen
          allowsPictureInPicture
        />
      ) : (
        <View style={styles.videoPlayer}>
          <Ionicons name="play-circle-outline" size={48} color="#DAA520" />
          <Text style={styles.playText}>Tidak ada video</Text>
        </View>
      )}

      <View style={styles.infoSection}>
        <Text style={styles.title}>{video?.title}</Text>
        <Text style={styles.uploader}>{video?.user?.name || video?.uploader_name || 'Unknown'}</Text>
        <Text style={styles.date}>{formatDate(video?.created_at)}</Text>

        <View style={styles.statsRow}>
          <View style={styles.statItem}>
            <Ionicons name="eye-outline" size={18} color="#999" />
            <Text style={styles.statText}>{video?.views_count || 0} views</Text>
          </View>

          <TouchableOpacity style={styles.statItem} onPress={handleLike}>
            <Ionicons
              name={liked ? 'heart' : 'heart-outline'}
              size={18}
              color={liked ? '#EF4444' : '#999'}
            />
            <Text style={[styles.statText, liked && { color: '#EF4444' }]}>
              {likesCount}
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      <Text style={styles.sectionTitle}>Komentar</Text>
    </View>
  );

  return (
    <View style={styles.container}>
      <FlatList
        data={comments}
        keyExtractor={(item) => item.id.toString()}
        renderItem={renderComment}
        ListHeaderComponent={renderHeader}
        refreshing={refreshing}
        onRefresh={handleRefresh}
        ListEmptyComponent={
          <Text style={styles.emptyText}>Belum ada komentar</Text>
        }
      />

      <View style={styles.commentInput}>
        <TextInput
          style={styles.input}
          placeholder="Tulis komentar..."
          placeholderTextColor="#999"
          value={newComment}
          onChangeText={setNewComment}
          multiline
        />
        <TouchableOpacity
          style={styles.sendBtn}
          onPress={handleAddComment}
          disabled={submitting}
        >
          {submitting ? (
            <ActivityIndicator size="small" color="#fff" />
          ) : (
            <Ionicons name="send" size={20} color="#fff" />
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f5f5f5' },
  videoPlayer: {
    backgroundColor: '#000', height: 200,
    justifyContent: 'center', alignItems: 'center',
  },
  playText: { color: '#DAA520', marginTop: 8, fontSize: 16, fontWeight: '600' },
  urlText: { color: '#777', fontSize: 11, marginTop: 4, paddingHorizontal: 20 },
  infoSection: { padding: 16 },
  title: { color: '#1a1a2e', fontSize: 18, fontWeight: 'bold' },
  uploader: { color: '#DAA520', fontSize: 14, marginTop: 4 },
  date: { color: '#777', fontSize: 12, marginTop: 2 },
  statsRow: { flexDirection: 'row', marginTop: 12, gap: 24 },
  statItem: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  statText: { color: '#888', fontSize: 14 },
  sectionTitle: {
    color: '#1a1a2e', fontSize: 16, fontWeight: 'bold',
    paddingHorizontal: 16, paddingBottom: 8,
    borderTopWidth: 1, borderTopColor: '#eee', paddingTop: 12,
  },
  commentItem: {
    paddingHorizontal: 16, paddingVertical: 10,
    borderBottomWidth: 1, borderBottomColor: '#eee',
  },
  commentHeader: { flexDirection: 'row', justifyContent: 'space-between' },
  commentUser: { color: '#DAA520', fontSize: 13, fontWeight: '600' },
  commentDate: { color: '#777', fontSize: 11 },
  commentContent: { color: '#666', fontSize: 13, marginTop: 4 },
  deleteBtn: { alignSelf: 'flex-end', marginTop: 4, padding: 4 },
  emptyText: { color: '#999', textAlign: 'center', padding: 20 },
  commentInput: {
    flexDirection: 'row', padding: 10, gap: 8,
    borderTopWidth: 1, borderTopColor: '#eee', backgroundColor: '#f5f5f5',
  },
  input: {
    flex: 1, backgroundColor: '#fff', borderRadius: 20,
    paddingHorizontal: 14, paddingVertical: 8, color: '#1a1a2e', fontSize: 14, maxHeight: 80,
    borderWidth: 1, borderColor: '#eee',
  },
  sendBtn: {
    backgroundColor: '#DAA520', borderRadius: 20,
    width: 40, height: 40, justifyContent: 'center', alignItems: 'center',
  },
});
