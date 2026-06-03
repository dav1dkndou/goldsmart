import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import VideoListScreen from '../screens/videos/VideoListScreen';
import VideoDetailScreen from '../screens/videos/VideoDetailScreen';
import UploadVideoScreen from '../screens/videos/UploadVideoScreen';

const Stack = createNativeStackNavigator();

export default function VideoStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: '#D4AF37' },
        headerTintColor: '#fff',
        headerTitleStyle: { fontWeight: 'bold' },
      }}
    >
      <Stack.Screen name="VideoList" component={VideoListScreen} options={{ title: 'Video' }} />
      <Stack.Screen name="VideoDetail" component={VideoDetailScreen} options={{ title: 'Detail Video' }} />
      <Stack.Screen name="UploadVideo" component={UploadVideoScreen} options={{ title: 'Upload Video' }} />
    </Stack.Navigator>
  );
}
