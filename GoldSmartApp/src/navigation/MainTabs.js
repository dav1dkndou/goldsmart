import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';

import HomeStack from './HomeStack';
import ProductStack from './ProductStack';
import MiningStack from './MiningStack';
import VideoStack from './VideoStack';
import ProfileStack from './ProfileStack';

const Tab = createBottomTabNavigator();

export default function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarIcon: ({ focused, color, size }) => {
          let iconName;

          if (route.name === 'HomeTab') {
            iconName = focused ? 'home' : 'home-outline';
          } else if (route.name === 'ProductsTab') {
            iconName = focused ? 'cube' : 'cube-outline';
          } else if (route.name === 'MiningTab') {
            iconName = focused ? 'hammer' : 'hammer-outline';
          } else if (route.name === 'VideosTab') {
            iconName = focused ? 'play-circle' : 'play-circle-outline';
          } else if (route.name === 'ProfileTab') {
            iconName = focused ? 'person' : 'person-outline';
          }

          return <Ionicons name={iconName} size={size} color={color} />;
        },
        tabBarActiveTintColor: '#D4AF37',
        tabBarInactiveTintColor: 'gray',
        tabBarStyle: {
          backgroundColor: '#fff',
          borderTopColor: '#eee',
        }
      })}
    >
      <Tab.Screen name="HomeTab" component={HomeStack} options={{ title: 'Home' }} />
      <Tab.Screen name="ProductsTab" component={ProductStack} options={{ title: 'Produk' }} />
      <Tab.Screen name="MiningTab" component={MiningStack} options={{ title: 'Mining' }} />
      <Tab.Screen name="VideosTab" component={VideoStack} options={{ title: 'Video' }} />
      <Tab.Screen name="ProfileTab" component={ProfileStack} options={{ title: 'Profil' }} />
    </Tab.Navigator>
  );
}
